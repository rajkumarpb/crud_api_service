# crud_api_service
PHP Service for building simple CRUD APIs

## Installation

In the console:

```
composer require akuehnis/crud_api_service
```

## Basic Usage

Simple Example using a Mysql Database

```php
// index.php

$api = new \Akuehnis\CrudApiService\Api('localhost', 'database_user', 'user_password', 'name_of_the_database');
$api->setTable('table_name');

$id = isset($_GET['id']) ? $_GET['id'] : null;

if ('GET' == $_SERVER['REQUEST_METHOD']) {
    if (null === $id) {
        list($data, $err) = $api->readAction($_GET);
    } else {
        list($data, $err) = $api->readOneAction($id);
    }
} elseif ('POST' == $_SERVER['REQUEST_METHOD']) {
    if (null === $id) {
        list($data, $err) = $api->createAction($_POST);
    } else {
        list($data, $err) = $api->updateAction($id, $_POST);
    }
} elseif ('DELETE' == $_SERVER['REQUEST_METHOD']) {
    if (null === $id) {
        $data = null;
        $err = 'No Id';
    } else{
        list($data, $err) = $api->deleteAction($id);
    }
} else {
    $data = null;
    $err = 'No Action';
}
if (null === $err) {
    header('Content-Type: application/json');
    echo json_encode($data);
} else {
    http_response_code(400);
    echo $err;
}

```

**Query for Data**

readAction takes one argument, an array of parameters. 
All queries are ADD, there is no possiblitiy for OR.
```php
$params = [
    'offset' => 20, // Query OFFSET
    'limit' => 10, // Query LIMIT, max number of datasets
    'order' => 'ASC', // ASC or DESC
    'order_by' => 'fieldname', 
    'fieldname' => 'exactvalue', // query for rows with exact match
    'number__gte' => 30,         // query for number greater or equal 30
    'number2__gt' => 20,         // query for numbere greater or equal 20
    'number__lt' => 40,          // query for number smaller than 40
    'number2__lte' => 40,         // query for number2 greater or equal 20
    'firsname__contains' => 'john',   // query firstname LIKE '%john%'
    'lastname__contains' => array(   // lastname LIKE '%doe% AND lastname LIKE '%smith%'
        'doe', 'smith'
        ),  
    'address__starts_with' => 'Alpha',  // address LIKE '%Alpha'
    'zip__in'=>'2000,2001,2002',  // zip IN (2000,2001,2002)
    'zip__not_in'=>'2000,2001,2002',  // zip NOT IN (2000,2001,2002)
    ];
```

**Get the total Count**

Use the second Argument of the readAction to retreive the
total number of rows with given params.

```
list($count, $err) = $api->readAction($params, true);
list($data, $err) = $api->readAction($params);
```

### Options

**Table Name**

Set the table name where the data are

```php
$api->setTableName('table_name');
```

**Define fields**

Default: 
* all fields can be read
* no field can be written (create and update)
* none is required

```php
$api->addField([
    'field'=>'title_de',
    'alias'=>'title',
    'type' => 'string',
    'read' => true,
    'create' => true,
    'create_required' => true,
    'update' => false,
    'update_required' => false,
])
->addField([
    'field'=>'active',
    'alias'=>'active',
    'type' => 'boolean',
    'read' => true,
    'create' => true,
    'create_required' => false,
    'update' => true,
    'update_required' => false,
])
...
;

```

**Validator Function**

Set a validator function applied to the data 
just before they are inserted or updated.

A validator function shall return NULL or an error message.

For create actions $id is NULL.

```php
$api->setValidator(function($data, $id){
    if ($condition) {
        return null; // ever
    } else {
        return 'This is an error';
    }
});
```

**Transformer Function**

Set a transformer function that modifies the 
data from the database into a format that the 
outside world sees.

The function argument is one row of data. It
is applied on readAction() and readOneAction()
just after reading the data from the database.

```php
$api->setTransformer(function($data){
    // This example replaces the field name row_id by id
    $data['id] = $data['row_id'];
    unset($data['row_id']);
    return $data;
});
```


**Reverse Transformer Function**

Set a reverse transformer function that modifies the 
data from the outside to a format that can be stored 
in the database.

This function is applied in createAction() and updateAction().

```php
$api->setReverseTransformer(function($data){
    // This example replaces the field name id by row_id
    $data['row_id] = $data['id'];
    unset($data['id']);
    return $data;
});
```

**Set Event Listener Function**

There are several event listeners:

* onAfterInsert
* onAfterUpdate
* onAfterDelete

The argument delivered to the listener function is the data array
as it is stored in the database.


```php
$api->setOnAfterDelete(function($data){
    // do something
});
```

### Advanced Options

**Set your own Db Connection**

The MySqlDbConnector is the default connector used. However, 
you may add your own. 

For example, if you are using this service with the 
Symfony Framework, you may use the dbal connector.

```php
$api = new \Akuehnis\CrudApiService\Api();
$api->setDbConnector($this->container->get('database_connector'));
```

**Join Tables**

Multiple Tables can be joined together for read operations.
For write opereations, joins are ignored.

```
$api->setTable('first_table')
    ->leftJoin([
        'table' => 'second_table', 
        'on' => 'first_table.second_id = second_table.id',
    ])
    ->leftJoin([
        'table' => 'third_table',
        'on' => 'second_table.rel_id = third_table.id',
        ])
    ;
```

Note: If two joined tables have similar field names, one must define
which of them shall be exposed. Use the addField-function to do that.

```
$api->addField([
    'field' => 'first_table.samefield',
    'alias' => 'samefield',
    'read' => true,
    ])
...
;
```

### Advanced Functions

The only parameter that transformer, validation or
event handler functions get, is the $data. If you need more,
you can supply it by using the 'use' statement.

Example, if you need the Service Container element in a
validation function:

```php
// assuming you are in a Symfony Controller

$container = $this->container;
$api->setValidator(function($data) use ($container) {
    // you may access $data and $container
});

```
