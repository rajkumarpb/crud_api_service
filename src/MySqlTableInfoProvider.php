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

class MySqlTableInfoProvider
{
    
    /*
     * db connector. 
     */
    public $conn = null;

    public $cache = array();


    public function __construct($db_connector) {
        $this->conn = $db_connector;
    }

    public function getTableInfo($table){
        if (!isset($this->cache[$table])){
            $sql = "SHOW FIELDS FROM `{$table}`"; 
            $arr = $this->conn->fetchAll($sql);
            $data = array();
            foreach ($arr as $row){
                $data[] = array(
                    'name' => $row['Field'],
                    'type' => $row['Type'],
                    'nullable' => 'Yes' == $row['Null'],
                    'default' => $row['Default'],
                    'extra' => $row['Extra'],
                    'identifier' => 'PRI' == $row['Key'],
                );
            }
            $this->cache[$table] = $data;
        }
        return $this->cache[$table];
    }

    public function getIdentifier($table){
        $primaries = array();
        foreach ($this->getTableInfo($table) as $row){
            if ($row['identifier']) {
                $primaries[] = $row['name'];
            }
        }
        return $primaries;
    }
}

