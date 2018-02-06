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

class Api
{
    
    /*
     * db connector. 
     */
    public $db_conn = null;

     /*
     * table info provider
     */
    public $table_info_provider = null;

    /*
     * db table name
     */
    public $table;

    /*
     * fields that can be read from table (read/read_one)
     */
    public $read_fields = '*';

    /*
     * fields that can be queried (read)
     */
    public $query_fields = '*';

    /*
     * fields that can be written (create/update)
     */
    public $write_fields = '*';

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
        // and the default TableInfoProvider for Mysql DBs
        if (null !== $host){
            $conn = new \Akuehnis\CrudApiService\MySqlDbConnector($host, $user, $pass, $name);
            $this->setDbConnector($conn);
            $this->setTableInfoProvider(new \Akuehnis\CrudApiService\MySqlTableInfoProvider($conn));
        }
        
    }

    public function setDbConnector($conn){
        $this->db_conn = $conn;
        $this->setTableInfoProvider(new \Akuehnis\CrudApiService\MySqlTableInfoProvider($conn));
        return $this;
    }

    public function setTableInfoProvider($table_info_provider){
        $this->table_info_provider = $table_info_provider;
        return $this;
    }

    public function setTable($table){
        $this->table = $table;
        return $this;
    }
    public function setReadFields($fields){
        $this->read_fields = $fields;
        return $this;
    }
    public function setWriteFields($fields){
        $this->write_fields = $fields;
        return $this;
    }
    public function setQueryFields($fields){
        $this->query_fields = $fields;
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
        $table   = $this->table;
        // Build the query
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
                $binds[] = $val;
            } else {
                $field = $a[0];
                switch ($a[1]){
                    case 'contains':
                        $where.= " AND `$field` IS NOT NULL AND `$field` LIKE '?'";
                        $binds[] = '%'.$val.'%';
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
                     case 'in':
                        $b = explode(',', $val);
                        $where.= " AND `$field` IS NOT NULL AND  `$field` IN ('".implode("','", $b).")";
                        break;
                     case 'not_in':
                        $b = explode(',', $val);
                        $where.= " AND `$field` NOT IN ('".implode("','", $b)."')";
                        break;
                    default:
                        continue;
                }
            }
        }
        $order_by = isset($query['order_by']) ? $query['order_by'] : '';
        if ('' == $order_by) {
            $order_by = $this->getIdentifier()[0];
        }
        $order_by = preg_replace('/[^A-Za-z0-9\_\-]/', '', $order_by);
        $order    = isset($query['order']) ? strtoupper($query['order']) :  'ASC';
        if (!in_array($order, ['ASC', 'DESC'])){
            $order = 'ASC';
        }
        $limit  = isset($query['limit']) ? intval($query['limit']) : 1000;
        $offset  = isset($query['offset']) ? intval($query['offset']) : 0;
        if ($get_count) {
            $sql_count = "SELECT COUNT(*) FROM `$table` WHERE $where";
            return array($this->db_conn->fetchColumn($sql_count, $binds), null);
        } else {
            if (is_array($this->read_fields) 
                && 0 < count($this->read_fields)
            ){
                $select = "`".implode("`,`", $this->read_fields)."`";
            } else {
                $select = "*";
            }
            $sql = "SELECT $select FROM `$table` WHERE $where 
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
        $identifier = $this->getIdentifier();
        $binds = array();
        if (is_array($this->read_fields) 
            && 0 < count($this->read_fields)
        ){
            $select = "`".implode("`,`", $this->read_fields)."`";
        } else {
            $select = "*";
        }
        $sql = "SELECT $select FROM `{$this->table}` WHERE ";
        if (!is_array($id) && 1 == count($identifier)){
            $sql.= " `{$identifier[0]}`=?";
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
        if (is_callable($this->reverse_transformer)) {
            $func = $this->reverse_transformer;
            $data = $func($data);
        }
        $data = $this->post_reverse_transform($data);
        if (is_callable($this->validator)) {
            $func = $this->validator;
            $err = $func($data);
            if (null !== $err){
                return array(null, $err);
            }
        }
        if (0 < $this->db_conn->insert($this->table, $data)){
            list($data, $err) = $this->readOneAction($this->db_conn->lastInsertId());
            if (null !== $err){
                return array(null, $err);
            } else {
                if (is_callable($this->on_after_insert)){
                    $func = $this->on_after_insert;
                    $func($data);
                }
                return array($data, null);
            }
        } else {
            return array(null, $this->db_conn->errorInfo()[2]);
        }
    }

    /*
     * returns tuple(data, error);
     */
    public function updateAction($id, $data)
    {

        if (is_callable($this->reverse_transformer)) {
            $func = $this->reverse_transformer;
            $data = $func($data);
        }
        $data = $this->post_reverse_transform($data);

        if (is_callable($this->validator)) {
            $func = $this->validator;
            $err = $func($data);
            if (null !== $err){
                return array(null, $err);
            }
        }
        $identifier = $this->getIdentifier();
        $cnt = $this->db_conn->update($this->table, $data, array($identifier[0] => $id));
        if (0 < $cnt) {
            list($new_data, $err) = $this->readOneAction($id);
            if (null !== $err){
                return array(null, $err);
            } else {
                if (is_callable($this->on_after_update)){
                    $func = $this->on_after_update;
                    $func($new_data);
                }
                return array($new_data, null);
            }
        } else {
            return array(null, $this->db_conn->errorInfo()[2]);
        }
    }

    /*
     * returns tuple(success, error);
     */
    public function deleteAction($id)
    {
        $identifier = $this->getIdentifier();
        $data = null;
        if (is_callable($this->on_after_delete)){
            // Save the data for the on_after_delete event
            list ($data, $err) = $this->readOneAction($id);
            if (null !== $err){
                return array(false, $err);
            }
        }
        if ($this->db_conn->delete($this->table, array(
            $identifier[0] => $id,
        ))){
            if (is_callable($this->on_after_delete)){
                $func = $this->on_after_delete;
                $func($data);
            }
            return array(true, null);
        } else {
            return array(false, $this->db_conn->errorInfo()[2]);
        }
    }

    /*
     * applied after custom transformer is applied
     * immediately before writing to db
     */
    public function post_reverse_transform($record) {
        $data = array();
        foreach ($record as $name => $rmt_value) {
            if (is_array($this->write_fields)
                && !in_array($name, $this->write_fields)
            ){
                throw new \Exception($name.' is not writable');
            }
            $field_type = $this->getFieldType($name);
            if (null === $field_type){
                throw new \Exception($name.' has no field type');
            }
            if ('time' == $field_type ) {
                if (preg_match('/^[0-9]{1,}:[0-9]{2}[:0-9{2}]$/', $rmt_value)){
                    $data[$name] = $rmt_value;
                } elseif (null === $rmt_value){
                    $data[$name] = null;
                }
            }
            elseif ('datetime' == $field_type) {
                if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/', $rmt_value)){
                    $data[$name] = $rmt_value;
                } elseif (null === $rmt_value) {
                    $data[$name] = null;
                }
            }
            elseif ('date' == $field_type) {
                if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $rmt_value)){
                    $data[$name] = $rmt_value;
                } elseif (null === $rmt_value) {
                    $data[$name] = null;
                }
            }
            elseif ('text' == $field_type) {
                $data[$name] = null === $rmt_value ? null : $this->filterText($rmt_value);
            }
            elseif ('string' == $field_type) {
                $data[$name] = null === $rmt_value ? null : $this->filterString($rmt_value);
            }
            elseif ('float' == $field_type) {
                $data[$name] = null === $rmt_value || '' == $rmt_value ? null : floatval($rmt_value);
            }
            elseif ('integer' == $field_type) {
                $data[$name] = null === $rmt_value || '' == $rmt_value ? null : intval($rmt_value);
            }
            elseif ('boolean' == $field_type) {
                if ('false' === $rmt_value || false === $rmt_value) {
                    $data[$name] = 0;
                } elseif ('true' === $rmt_value) {
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
        foreach ($record as $name => $val) {
            if (is_array($this->read_fields)
              && !in_array($name, $this->read_fields)){
                continue;
            }
            $field_type = $this->getFieldType($name);
            if (null === $field_type){
                throw new \Exception($name.' has no field type');
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
        foreach ($this->getTableInfo() as $row){
            if ($row['name'] == $name) {
                if (0 === strpos($row['type'], 'tinyint(1)')){
                    return 'boolean';
                }
                if (false !== strpos($row['type'], 'int')){
                    return 'integer';
                }
                if (false !== strpos($row['type'], 'decimal')){
                    return 'float';
                }
                if (false !== strpos($row['type'], 'float')){
                    return 'float';
                }
                if (false !== strpos($row['type'], 'datetime')){
                    return 'datetime';
                }
                if (false !== strpos($row['type'], 'date')){
                    return 'date';
                }
                if (false !== strpos($row['type'], 'time')){
                    return 'time';
                }
                if (false !== strpos($row['type'], 'char')){
                    return 'string';
                }
                if (false !== strpos($row['type'], 'text')){
                    return 'text';
                }
                if (false !== strpos($row['type'], 'blob')){
                    return 'blob';
                }
                return 'undefined';
            }
        }

        return null; //not found
    }

    /* 
     * returns []primary
     */
    public function getIdentifier(){
        return $this->table_info_provider->getIdentifier($this->table);
    }
   
    /* 
     * returns []primary
     */
    public function getTableInfo(){
        return $this->table_info_provider->getTableInfo($this->table);
    }

}
