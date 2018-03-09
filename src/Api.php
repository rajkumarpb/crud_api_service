<?php
/*
 * This file is part of the CrudApi package.
 *
 * (c) Adrian Kuehnis <webrequest@azular.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Akuehnis\CrudApiService;
use  Akuehnis\CrudApiService\MySqlDbConnector;

class Api
{
    
    /*
     * db connector. 
     */
    public $db_conn = null;

    /*
     * db table name
     */
    public $table;

    /*
     * table left joins
     *
     * a join is an array(table: 'tablename', on: 'on-condition')
     */
    public $left_join = [];

    /*
     * fields this api is aware of
     * if '*' (default) all fields can be read and written
     *
     * use function addField([]) to add a field
     */
    public $fields = '*';

    /*
     * array of primary keys
     */
    public $primary = [];
    
    /*
     * allow row deletion
     *
     */
    public $allow_delete = true;

    /*
     * validate callable
     *
     */
    public $validator;

    /*
     * transform callable
     *
     * from database to outside world
     *
     */
    public $transformer;

    /*
     * reverse transform callable
     *
     * from outside world to database
     *
     */
    public $reverse_transformer;

    /*
     * inserted callable
     *
     */
    public $on_after_insert;

    /*
     * updated callable
     *
     */
    public $on_after_update;
    
    /*
     * deleted callable
     *
     */
    public $on_after_delete;


    public function __construct($host=null, $user=null, $pass=null, $name=null) {

        // if host is set, use default Mysql Db Connector
        if (null !== $host){
            $conn = new MySqlDbConnector($host, $user, $pass, $name);
            $this->setDbConnector($conn);
        }
        
    }

    public function setDbConnector($conn){
        $this->db_conn = $conn;
        return $this;
    }

    public function setTable($table){
        $this->table = $table;
        return $this;
    }
    public function leftJoin($join){
        $this->left_join[] = $join;
        return $this;
    }
    public function setAllowDelete($value){
        $this->allow_delete = $value;
        return $this;
    }

    /* can be a string (default values apply)
     * or {
     * name: 
     * alias:
     * type:
     * read: true|false
     * create: true|false
     * update: true|false
     * type: integer|boolean|text|string|date|datetime|time
     * }
     */ 
    public function addField($field){
        if (!is_array($this->fields)){
            $this->fields = [];
        }
        $default = array(
            'field' => '',
            'alias' => '',
            'type' => 'string',
            'read' => true,
            'create' => false,
            'create_required' => false,
            'update' => true,
            'update_required' => false,
        );
        if (!is_array($field)){
            $default['field'] = $field;
            $default['alias'] = $field;
        } else {
            foreach ($field as $key => $val) {
                $default[$key] = $val;
                if ('field' == $key && !isset($field['alias'])){
                    $default['alias'] = $val;
                }
            }
        }
        $this->fields[] = $default;
        return $this;
    }

    public function setValidator($function){
        $this->validator = $function;
        return $this;
    }
    public function setTransformer($function){
        $this->transformer = $function;
        return $this;
    }
    public function setReverseTransformer($function){
        $this->reverse_transformer = $function;
        return $this;
    }
    public function setOnAfterInsert($function){
        $this->on_after_insert = $function;
        return $this;
    }
    public function setOnAfterUpdate($function){
        $this->on_after_update = $function;
        return $this;
    }
    public function setOnAfterDelete($function){
        $this->on_after_delete = $function;
        return $this;
    }

    /* 
    *
    *  query parameters:
     * order_by string fieldname
     * order    string asc|desc
     * limit    integer defaults to 1000
     * offset   integer defaults to 0
     *
     * query search options
     * [fieldname]:             value 
     * [fieldname]__startswith: value
     * [fieldname]__gte:        value
     * [fieldname]__contains:   value
     * [fieldname]__in:         csv values
     * 
     * Example: 
     *
     * list(results, $err) = $api->readAction(array(
     *      'qty__lte' => 20,
     *      'name__contains' => 'peter',
     *      'category_id__in' => '2,3,10',
     *      'order_by' => 'created',
     *      'order' => 'ASC',
     *      'offset' => 10,
     *      'limit' => 2000,
     *      ), false);
     *
     * Search follows these rules: https://docs.djangoproject.com/en/2.0/ref/models/querysets/
     *
     * returns tuple(data, error);
     */
    public function readAction($query = array(), $get_count = false)
    {
        if (null === $this->table){
            return array(null, 'table undefined');
        }
        $table   = $this->table;
        foreach ($this->left_join as $left_join){
            $table.= ' LEFT JOIN '.$left_join['table'].' ON '.$left_join['on'];
        }

        $where = " 1 ";
        $binds = array();

        foreach ($query as $key => $val) {
            // Todo: val can be array (multiple AND for search)
            if (in_array($key, ['order_by', 'order', 'limit', 'offset'])){
                continue;
            }
            $a = explode('__', $key);
            if (1 == count($a)){
                $where.= " AND `$key`=?";
                if ('boolean' == $this->getFieldType($key)) {
                    if ('true' == $val){
                        $binds[] = 1;
                    } elseif ('false' == $val) {
                        $binds[] = 0;
                    } else {
                        $binds[] = $val;
                    }
                } else {
                    $binds[] = $val;
                }
            } else {
                $field = str_replace('.', '`.`', $a[0]);
                switch ($a[1]){
                    case 'contains':
                        if (is_array($val)){
                            foreach ($val as $v) {
                                $where.= " AND `$field` IS NOT NULL AND `$field` LIKE ?";
                                $binds[] = '%'.$v.'%';
                            }
                        } else {
                            $where.= " AND `$field` IS NOT NULL AND `$field` LIKE ?";
                            $binds[] = '%'.$val.'%';
                        }
                        break;
                    case 'lt':
                        $where.= " AND `$field` IS NOT NULL AND `$field` < ?";
                        $binds[] = $val;
                        break;
                    case 'lte':
                        $where.= " AND `$field` IS NOT NULL AND `$field` <= ?";
                        $binds[] = $val;
                        break;
                    case 'gt':
                        $where.= " AND `$field` IS NOT NULL AND `$field` > ?";
                        $binds[] = $val;
                        break;
                    case 'gte':
                        $where.= " AND `$field` IS NOT NULL AND `$field` >= ?";
                        $binds[] = $val;
                        break;
                    case 'startswith':
                        $where.= " AND `$field` IS NOT NULL AND `$field` LIKE '?'";
                        $binds[] = '%'.$val;
                        break;
                    case 'endswith':
                        $where.= " AND `$field` IS NOT NULL AND `$field` LIKE '?'";
                        $binds[] = $val.'%';
                        break;
                     case 'in':
                        $b = explode(',', $val);
                        $where.= " AND `$field` IS NOT NULL AND  `$field` IN ('".implode("','", $b)."')";
                        break;
                     case 'not_in':
                        $b = explode(',', $val);
                        $where.= " AND (`$field` IS NULL OR `$field` NOT IN ('".implode("','", $b)."'))";
                        break;
                    case 'isnull':
                        if('true' == $val) {
                            $where.= " AND `$field` IS NULL";
                        } else {
                            $where.= " AND `$field` IS NOT NULL";
                        }
                        break;
                    default:
                        continue;
                }
            }
        }
        $order_by = isset($query['order_by']) ? $query['order_by'] : '';
        if ('' == $order_by) {
            $order_by = $this->getIdentifier();
        }
        $order_by = preg_replace('/[^A-Za-z0-9\_\-]/', '', $order_by);
        $order    = isset($query['order']) ? strtoupper($query['order']) :  'ASC';
        if (!in_array($order, ['ASC', 'DESC'])){
            $order = 'ASC';
        }
        $limit  = isset($query['limit']) ? intval($query['limit']) : 1000;
        $offset  = isset($query['offset']) ? intval($query['offset']) : 0;
        if ($get_count) {
            $sql_count = "SELECT COUNT(*) FROM $table WHERE $where";
            return array($this->db_conn->fetchColumn($sql_count, $binds), null);
        } else {
            if (is_array($this->fields) 
                && 0 < count($this->fields)
            ){
                $select = '';
                $sep = '';
                foreach ($this->fields as $read_field){
                    $select.= $sep."`".str_replace('.', '`.`', $read_field['field'])."`".
                        " AS `".$read_field['alias']."`";
                    $sep = ',';
                }
            } else {
                $select = "*";
            }
            $sql = "SELECT $select FROM $table WHERE $where 
                ORDER BY $order_by $order
                LIMIT $limit OFFSET $offset";
            $data = $this->db_conn->fetchAll($sql, $binds);
            for($i=0;$i<count($data);$i++){
                $data[$i] = $this->pre_transform($data[$i]);
                if (is_callable($this->transformer)) {
                    $func = $this->transformer;
                    $data[$i] = $func($data[$i]);
                }
            }
            return array($data, null);
        }
    }
    
    /*
     * @input $id primary key
     * Todo: $id can be associative array for concat primary
     * returns tuple(data, error);
     */
    public function readOneAction($id)
    {
        if (null === $this->table){
            return array(null, 'table undefined');
        }
        $table   = $this->table;
        foreach ($this->left_join as $left_join){
            $table.= ' LEFT JOIN '.$left_join['table'].' ON '.$left_join['on'];
        }
        $binds = array();
        if (is_array($this->fields) 
            && 0 < count($this->fields)
        ){
            $select = '';
            $sep = '';
            foreach ($this->fields as $read_field){
                $select.= $sep.
                    "`".str_replace('.', '`.`', $read_field['field'])."`".
                    " AS `".$read_field['alias']."`";
                $sep = ',';
            }
        } else {
            $select = "*";
        }
        $sql = "SELECT $select FROM {$table} WHERE ";
        if (is_array($id)){
            $sep = '';
            foreach ($id as $key => $v){
                $sql.= $sep." `{$key}`=?";
                $binds[] = $v;
                $sep = ' AND ';
            }
        } else {
            $sql.= " `{$this->getIdentifier()}`=?";
            $binds[] = $id;
        }
        $data = $this->db_conn->fetchAll($sql, $binds);
        if (0 == count($data)){
            return array(null, 404);
        } else {
            $data = $this->pre_transform($data[0]);
            if (is_callable($this->transformer)){
                $func = $this->transformer;
                $data = $func($data);
            }
            return array($data,null);
        }
    }
    
    /*
     * returns tuple(data, error);
     */
    public function createAction($data)
    {
        if (null === $this->table){
            return array(null, 'table undefined');
        }
        if ('*' == $this->fields){
            return array(null, 'Write protected');
        }
        // Check if required fields are here
        $create_keys = array();
        $create_required_keys = array();
        foreach ($this->fields as $field){
            if ($field['create']){
                $create_keys[] = $field['alias'];
                if ($field['create_required']){
                    $create_required_keys[] = $field['alias'];
                }
            }
        }
        $keys = array_keys($data);
        $diff = array_diff($keys,$create_keys);
        if (0 < count($diff)){
            return array(null, 'Not allowed: '.implode(',',$diff));
        }
        $diff = array_diff($create_required_keys, $keys);
        if (0 < count($diff)){
            return array(null, 'Required: '.implode(',',$diff));
        }

        if (is_callable($this->validator)) {
            $func = $this->validator;
            $err = $func($data, null);
            if (!in_array($err, [null, true], true)){
                return array(null, $err);
            }
        }

        if (is_callable($this->reverse_transformer)) {
            try {
                $func = $this->reverse_transformer;
                $data = $func($data);
            } catch (\Exception $e){
                return array(null, $e->getMessage());
            }
        }
        // post_reverse_transform required before validation 
        // to get a normalization of the values
        try {
            $data = $this->post_reverse_transform($data); 
        } catch (\Exception $e) {
            return array(null, $e->getMessage());
        }
        
        try {
            $this->db_conn->insert($this->table, $data);
            list($data, $err) = $this->readOneAction($this->db_conn->lastInsertId());
            if (null !== $err){
                return array(null, $err);
            }
            if (is_callable($this->on_after_insert)){
                $func = $this->on_after_insert;
                $func($data);
            }
            return array($data, null);
        } catch (\Exception $e) {
            return array(null, $e->getMessage());
        }
    }

    /*
     * returns tuple(data, error);
     */
    public function updateAction($id, $data)
    {
        if ('*' == $this->fields){
            return array(null, 'Write protected');
        }
        if (null === $this->table){
            return array(null, 'table undefined');
        }
        // Check if required fields are here
        $update_keys = array();
        $update_required_keys = array();
        foreach ($this->fields as $field){
            if ($field['update']){
                $update_keys[] = $field['alias'];
                if ($field['update_required']){
                    $update_required_keys[] = $field['alias'];
                }
            }
        }
        $keys = array_keys($data);
        $diff = array_diff($keys,$update_keys);
        if (0 < count($diff)){
            return array(null, 'Not allowed: '.implode(',',$diff));
        }
        $diff = array_diff($update_required_keys, $keys);
        if (0 < count($diff)){
            return array(null, 'Required: '.implode(',',$diff));
        }

        if (is_callable($this->validator)) {
            $func = $this->validator;
            $err = $func($data, $id);
            if (!in_array($err, [null, true], true)){
                return array(null, $err);
            } 
        }
        if (is_callable($this->reverse_transformer)) {
            try {
                $func = $this->reverse_transformer;
                $data = $func($data);
            } catch (\Exception $e) {
                return array(null, $e->getMessage());
            }
        }
        // post_reverse_transform required before validation 
        // to get a normalization of the values
        try {
            $data = $this->post_reverse_transform($data); 
        } catch (\Exception $e) {
            return array(null, $e->getMessage());
        }
        
        if (is_array($id)){
            $where = $id;
        } else {
            $where = array($this->getIdentifier() => $id);
        }

        try {
            $this->db_conn->update($this->table, $data, $where);
            list($new_data, $err) = $this->readOneAction($id);
            if (null !== $err){
                return array(null, $err);
            } 
            if (is_callable($this->on_after_update)){
                $func = $this->on_after_update;
                $func($new_data);
            }
            return array($new_data, null);
        } catch (\Exception $e) {
            return array(null, $e->getMessage());
        }
    }

    /*
     * returns tuple(success, error);
     */
    public function deleteAction($id)
    {
        if (null === $this->table){
            return array(null, 'table undefined');
        }
        $identifier = $this->getIdentifier();
        $data = null;
        if (is_callable($this->on_after_delete)){
            // Save the data for the on_after_delete event
            list ($data, $err) = $this->readOneAction($id);
            if (null !== $err){
                return array(false, $err);
            }
        }
        try {
            if (is_array($id)){
                $where = $id;
            } else {
                $where = array(
                    $identifier => $id,
                );
            }
            $this->db_conn->delete($this->table, $where);
            if (is_callable($this->on_after_delete)){
                $func = $this->on_after_delete;
                $func($data);
            }
            return array(true, null);
        } catch (\Exception $e){
            return array(false, $e->getMessage());
        }
    }

    /*
     * applied after custom transformer is applied
     * immediately before writing to db
     */
    public function post_reverse_transform($record) {
        $data = array();
        foreach ($record as $name => $rmt_value) {
            $field_type = $this->getFieldType($name);
            if ('time' == $field_type ) {
                if (preg_match('/^[0-9]{1,}:[0-9]{2}[:0-9{2}]$/', $rmt_value)){
                    $data[$name] = $rmt_value;
                } elseif (empty($rmt_value)){
                    $data[$name] = null;
                }
            }
            elseif ('datetime' == $field_type) {
                if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/', $rmt_value)){
                    $data[$name] = $rmt_value;
                } elseif (empty($rmt_value)) {
                    $data[$name] = null;
                }
            }
            elseif ('date' == $field_type) {
                if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $rmt_value)){
                    $data[$name] = $rmt_value;
                } elseif (empty($rmt_value)) {
                    $data[$name] = null;
                }
            }
            elseif ('text' == $field_type) {
                $data[$name] = null === $rmt_value ? null : $this->filterText($rmt_value);
            }
            elseif ('string' == $field_type) {
                $data[$name] = null === $rmt_value ? null : $this->filterString($rmt_value);
            }
            elseif ('float' == $field_type  || 'decimal' == $field_type) {
                $data[$name] = in_array($rmt_value, [null,'',false], true) ? null : floatval($rmt_value);
            }
            elseif ('integer' == $field_type) {
                $data[$name] = in_array($rmt_value, [null,'',false], true) ? null : intval($rmt_value);
            }
            elseif ('boolean' == $field_type) {
                if ('false' === $rmt_value || false === $rmt_value) {
                    $data[$name] = 0;
                } elseif ('true' === $rmt_value || true === $rmt_value) {
                    $data[$name] = 1;
                } else {
                    $data[$name] = $rmt_value;
                }
            }
            else {
                $data[$name] = null === $rmt_value ? null : $this->filterString($rmt_value);
            }
        }
        return $data;
    }

    /*
     * returns array
     *
     * applied before custom transformer is applied
     * immediately after reading from db
     */
    public function pre_transform($record) {
        $data = array();
        $field_type = null;
        foreach ($record as $name => $val) {
            $field_type = $this->getFieldType($name);
            if (null === $field_type){
                throw new \Exception($name.' has no field type');
                return;
            }
            if ('time' == $field_type ) {
                $data[$name] = null === $val ? null : $val;
            }
            elseif ('datetime' == $field_type) {
                $data[$name] = null === $val ? null : $val;
            }
            elseif ('date' == $field_type) {
                $data[$name] = null === $val ? null : $val;
            }
            elseif ('text' == $field_type) {
                $data[$name] = $val;
            }
            elseif ('string' == $field_type) {
                $data[$name] = $val;
            }
            elseif ('float' == $field_type) {
                $data[$name] = null === $val ? null : floatval($val);
            }
            elseif ('integer' == $field_type) {
                $data[$name] = null === $val ? null : intval($val);
            }
            elseif ('boolean' == $field_type) {
                $data[$name] = null === $val ? null : (boolean)$val;
            }
            else {
                $data[$name] = $val;
            }
        }
        return $data;
    }

    public function filterString($value)
    {
        $f = array(chr(0),chr(1),chr(2),chr(3),chr(4),chr(5),chr(6),chr(7),
              chr(8),chr(11),chr(12),chr(14),chr(15),chr(16),chr(17),
              chr(18),chr(19), "\n","\r");
        return str_replace($f,' ',$value);
    }

    public function filterText($value)
    {
        $f = array(chr(0),chr(1),chr(2),chr(3),chr(4),chr(5),chr(6),chr(7),
              chr(8),chr(11),chr(12),chr(14),chr(15),chr(16),chr(17),
              chr(18),chr(19));
        return str_replace($f,' ',$value);
    }

    

    public function getFieldType($name){
        if (!is_array($this->fields)){
            return 'string';
        }
        foreach ($this->fields as $field){
            if ($field['alias'] == $name){
                return $field['type'];
            }
        }
        return 'string';
    }

    /* 
     * returns []primary
     */
    public function getIdentifier(){
        return $this->primary;
    }

    public function setIdentifier($field_name){
        $this->primary = $field_name;
        return $this;
    }
   

}
