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
    
    public $settings = array(
        'username'  => '',
        'password'  => '',
        'host'      => 'localhost',
        'database'  => '',
        'table'     => '',
        'read'      => '*', // * or []string fieldname
        'write'     => '*', // * or []string fieldname
    );

    /*
     * db connection
     */
    public $conn = null;

    /*
     * cache table info
     */
    public $table_info_cache = null;

    public function __construct($settings) {
        $this->settings = array_merge($this->settings, $settings);

        $name = $this->settings['database'];
        $host = $this->settings['host'];
        $user = $this->settings['username'];
        $pass = $this->settings['password'];
        $options = array(
            \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
        );

        $dsn = 'mysql:dbname='.$name. ';host='. $host;
        $this->conn = new \PDO($dsn, $user, $pass, $options);
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
        $table   = $this->settings['table'];
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
                    default:
                        continue;
                }
            }
        }
        $order_by = isset($params['order_by']) ? $params['order_by'] : '';
        if ('' == $order_by) {
            $order_by = $this->getIdentifier()[0];
        }
        $order_by = preg_replace('/[^A-Za-z0-9\_\-]/', '', $order_by);
        $order    = isset($params['order']) ? strtoupper($params['order']) :  'ASC';
        if (!in_array($order, ['ASC', 'DESC'])){
            $order = 'ASC';
        }
        $limit  = isset($params['limit']) ? intval($params['limit']) : 1000;
        $offset  = isset($params['offset']) ? intval($params['offset']) : 0;
        if ($get_count) {
            $sql_count = "SELECT COUNT(*) FROM `$table` WHERE $where";
            return $this->fetchColumn($sql_count, $binds);
        } else {
            if (is_array($this->settings['read']) 
                && 0 < count($this->settings['read'])
            ){
                $select = "`".implode("`,`", $this->settings['read'])."`";
            } else {
                $select = "*";
            }
            $sql = "SELECT $select FROM `$table` WHERE $where 
                ORDER BY $order_by $order
                LIMIT $limit OFFSET $offset";
            $data = $this->fetchAll($sql, $binds);
            for($i=0;$i<count($data);$i++){
                $data[$i] = $this->readRecord($data[$i]);
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
        if (is_array($this->settings['read']) 
            && 0 < count($this->settings['read'])
        ){
            $select = "`".implode("`,`", $this->settings['read'])."`";
        } else {
            $select = "*";
        }
        $sql = "SELECT $select FROM `{$this->settings['table']}`
            WHERE ";
        if (!is_array($id) && 1 == count($identifier)){
            $sql.= " `{$identifier[0]}`=?";
            $binds[] = $id;
        }
        $data = $this->fetchAll($sql, $binds);
        if (0 == count($data)){
            return array(null, 'Not found');
        } else {
            return array($this->readRecord($data[0]),null);
        }
    }
    
    /*
     * returns tuple(data, error);
     */
    public function createAction($data)
    {
        var_dump($data);
        list($data, $err) = $this->writeRecord($data);
        if (null !== $err){
            return array(null, $err);
        }
        if (0 < $this->insert($this->settings['table'], $data)){
            return $this->readOneAction($this->lastInsertId());
        } else {
            return array(null, $this->conn->errorInfo()[2]);
        }
    }

    /*
     * returns tuple(data, error);
     */
    public function updateAction($id, $data)
    {
        list($data, $err) = $this->writeRecord($data);
        if (null !== $err){
            return array(null, $err);
        }
        $identifier = $this->getIdentifier();
        if (0 < $this->update($this->settings['table'], $data, array($identifier[0] => $id))) {
            return $this->readOneAction($id);
        } else {
            return array(null, $this->conn->errorInfo()[2]);
        }
    }

    /*
     * returns tuple(success, error);
     */
    public function deleteAction($id)
    {
        $identifier = $this->getIdentifier();
        if ($this->delete($this->settings['table'], array(
            $identifier[0] => $id,
        ))){
            return array(true, null);
        } else {
            return array(false, $this->conn->errorInfo()[2]);
        }
    }

    public function writeRecord($record) {
        $data = array();
        foreach ($record as $name => $rmt_value) {
            if (is_array($this->settings['write'])
                && !in_array($name, $this->settings['write'])
            ){
                return array(null, $name.' is not writable');
            }
            $field_type = $this->getFieldType($name);
            if (null === $field_type){
                return array(null, $name.' has no field type');
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
            elseif ('decimal' == $field_type) {
                $data[$name] = null === $rmt_value ? null : floatval($rmt_value);
            }
            elseif ('integer' == $field_type) {
                $data[$name] = null === $rmt_value ? null : intval($rmt_value);
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
        return array($data, null);
    }

    /*
     * returns array
     */
    public function readRecord($record) {
        $data = array();
        foreach ($record as $name => $val) {
            $field_type = $this->getFieldType($name);
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
            elseif ('decimal' == $field_type) {
                $data[$name] = floatval($val);
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

    public function getTableInfo(){
        if (null === $this->table_info_cache) {
            $sql = "SHOW FIELDS FROM `{$this->settings['table']}`"; 
            $sth = $this->conn->prepare($sql);
            $sth->execute(array());
            $this->table_info_cache = $sth->fetchAll(\PDO::FETCH_ASSOC);
        }
        return $this->table_info_cache;
    }

    public function getFieldType($name){
        foreach ($this->getTableInfo() as $row){
            if ($row['Field'] == $name) {
                if (0 === strpos($row['Type'], 'tinyint(1)')){
                    return 'boolean';
                }
                if (false !== strpos($row['Type'], 'int')){
                    return 'integer';
                }
                if (false !== strpos($row['Type'], 'decimal')){
                    return 'decimal';
                }
                if (false !== strpos($row['Type'], 'datetime')){
                    return 'datetime';
                }
                if (false !== strpos($row['Type'], 'date')){
                    return 'date';
                }
                if (false !== strpos($row['Type'], 'time')){
                    return 'time';
                }
                if (false !== strpos($row['Type'], 'char')){
                    return 'string';
                }
                if (false !== strpos($row['Type'], 'text')){
                    return 'text';
                }
                if (false !== strpos($row['Type'], 'blob')){
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
        $primaries = array();
        foreach ($this->getTableInfo() as $row){
            if (isset($row['Key']) && 'PRI' == $row['Key']) {
                $primaries[] = $row['Field'];
            }
        }
        return $primaries;
    }

    public function fetchColumn($sql, $binds = array()) {
        $sth = $this->conn->prepare($sql);
        $sth->execute($binds);
        return $sth->fetchColumn();
    }

    public function fetchAll($sql, $binds = array()){
        $sth = $this->conn->prepare($sql);
        $sth->execute($binds);
        return $sth->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function insert($table, $data){
        $sql = "INSERT INTO `$table` (`".
            implode('`,`', array_keys($data))."`)
            VALUES (". implode(',', array_fill(0, count($data), '?')).")";

        $sth = $this->conn->prepare($sql);
        if( false === $sth ) {
            return false;
        }
        return $sth->execute(array_values($data));

    }
    public function update($table, $data, $where){
        $params = array();
        $sep = ' SET ';
        $sql = "UPDATE `$table`";
        foreach($data as $key=>$val){
            $sql .= $sep.$key.'=?';
            $binds[] = $val;
            $sep = ',';
        }
        $sep = ' WHERE ';
        foreach($where as $key=>$val){
            $sql.= $sep.$key."=?";
            $binds[] = $val;
            $sep = " AND ";
        }
        $sth = $this->conn->prepare($sql);
        if( false === $sth ) {
            return false;
        }
        return $sth->execute($binds);
    }

    public function lastInsertId(){
        return $this->conn->lastInsertId();
    }

    public function delete($table, $where){
        $sql = "DELETE FROM $table WHERE ";
        $sep = '';
        $binds = array();
        foreach($where as $key=>$val){
            $sql.= $sep.$key.'=?';
            $sep = ' AND ';
            $binds[] = $val;
        }
        $sth = $this->conn->prepare($sql);
        if( false === $sth ) {
            return false;
        }
        return $sth->execute($binds);
    }

}
