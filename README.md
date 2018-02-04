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

$api = new $api = new \Akuehnis\CrudApiService\Api('localhost', 'database_user', 'user_password', 'name_of_the_database');
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
#
### Options

**Table Name**

Set the table name where the data are

```php
$api->setTableName('table_name');
```

**Read Fields**

Set the names of those fields that should be read 
from the table in readAction() or readOneAction()

```php
$api->setReadFields('*'); // all, this is the default
$api->setReadFields(['firstname', 'lastname']); // just two fields

```

**Write Fields**

Set the names of those fields that could be written
from the table in updateAction() or createAction()

```php
$api->setWriteFields('*'); // all, this is the default
$api->setWriteFields(['firstname', 'lastname']); // just two fields
```

**Query Fields**

Set the names of those fields for which can be searched
in a readAction()
from the table in updateAction() or createAction()

```php
$api->setQueryFields('*'); // all, this is the default
$api->setQueryFields(['firstname', 'lastname']); // just two fields
```

**Validator Function**

Set a validator function applied to the data 
just before they are inserted or updated.

A validator function shall return NULL or an error message

```php
$api->setValidato(function($data){
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

** Set Event Listener Function **

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
