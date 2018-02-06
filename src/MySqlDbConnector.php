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

class MySqlDbConnector
{
    
    /*
     * db connector. 
     */
    public $conn = null;


    public function __construct($host, $user, $pass, $name) {
        $options = array(
            \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
        );

        $dsn = 'mysql:dbname='.$name. ';host='. $host;
        $this->conn = new \PDO($dsn, $user, $pass, $options);
    }

    /*
    public function getTableInfo($table){
        $sql = "SHOW FIELDS FROM `{$table}`"; 
        $sth = $this->conn->prepare($sql);
        $sth->execute(array());
        return $sth->fetchAll(\PDO::FETCH_ASSOC);
    }
     */

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
    public function errorInfo(){
        return $this->conn->errorInfo();
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
