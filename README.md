# U2 Web Development Environment (RedBack) for PHP [![Build Status](https://travis-ci.org/gheydon/redback.png)](https://travis-ci.org/gheydon/redback)
Allows native communitcation with RocketSoftware’s U2 database Enviroments using the U2 Web Development Environment, and also providing methods which allow PHP to make greater use of the native U2 dynamic arrays.
## Compatibility
### U2 Web Development Environment (RedBack) 4
Full support for Web DE 4.2.16+ in native PHP.
### U2 Web Development Environment (RedBack) 5
Currently not supported. This is being currently worked on, and any support will be much appreciated.

The major problem with version 5 is that it is no longer running on the old Redback Scheduler, but instead running over UniObjects, so to talk to the the U2 Web DE we need to be able to talk UniObjects. I have started decoding the protocole, and starting an implementation a functioning PHP version, but this will take time.

However because I needed to build the uarray libraries to work with U2 Dynamic Arrays the actual handling of the raw data is almost done. There are areas which will need to be improved, such needing to be able to handle the internal data formats like dates and numbers which will mean that I will need to implement OCONV and ICONV methods to the uArray object.

## Installation
Installation via [composer](http://getcomposer.com) is the only method supported at this stage. Edit your composer.json to add the following.
``` js
{
    "require": {
        // ...
        “heydon/redback”: “1.0.x”
    }
}
```

## Usage
### Initilising the RedBack object
To create a new uObject (RedBack connection object) which will allow access to the u2 Web Development Enviroment.   
``` php
$object = new RocketSoftware\u2\RedBack\uObject();
```
### Connecting to the Web DE Server
To connect to the U2 Web DE server you will require the connection object for [RedBack 4](https://github.com/gheydon/redback4). Once this is install you will be able to use the following to create the connection.   
``` php
$object->connect(‘RedBack4://127.0.0.1:8401’);
```
### Opening a Web DE Object
To open an object use the following.   
``` php
$object->open('EXMOD:Employee’);
```
### Authentication
To authenticate your object against the server, a user and password can be passed while opening.   
``` php
$object->open(‘EXMOD:Employee’, ‘rbadmin’, ‘redback’);
```
### Quick Open
All of the above can be put together for connecting quickly.   
``` php
$object = new RocketSoftware\u2\RedBack\uObject(‘RedBack4://127.0.0.1:8401’, ‘EXMOD:Employee’, ‘rbadmin’, ‘redback’);
```
### Setting a property
You can set properties used which have been configured in the RBO.   
``` php
$object->Name = ‘Test name’;
```
where name is the property “Name”. If the property contains a ‘.’ then to access the property using the uObject::set() method.
### Getting the value of a property
The same as setting a property you can also do the following:-   
``` php
$name = $object->Name;
```
As with settings properties with a ‘.’ in the property name you can use the uObject::get() method.
### Calling a method
Calling methods the same as the calling methods on the standard PHP object.   
``` php
$object->ReadData();
```
However there are also special methods, “Select” and “DispPage” which are on uQuery objects. in this case the uObject will return a uQuery object.   
``` php
$rs = $object->Select();
```
### Using the uQuery object
The uQuery object is a standard PHP iterator and can be used with foreach() and other methods to use iterators.   
``` php
foreach ($rs as $key => $item) {   
  echo “FirstName: {$item[‘FIRST.NAME’]}<br/>”;   
}
```

uQuery doesn’t really work like a normal recordset where each record is retreived 1 at a time, but instead works by downloading an entire page in at a time. The page size is determinded by the ‘items_per_page’ which is set in the RBO and can also be set at runtime, before the initial select is called.

Given this to allow paging via the object you can use the `::getPage()` to get the page of data that you want.

``` php
$page = $object->Select()->getPage(2);
foreach ($page as $key => $item) {
  echo “FirstName: {$item[‘FIRST.NAME’]}<br/>”;
}
```

As you can see from the example above you can chain the getPage() command to get the additional information.

##Symfony
To use Redback in symfony you can create the RedBack object as a service, which can then be access.

``` yml
services:
  redback:
    class: RocketSoftware\u2\RedBack\uObject
    arguments: [“RedBack4://127.0.0.1:8401”, NULL, NULL, NULL, FALSE]
    scope: prototype
```
To get the RedBack object in a controler do the following.
``` php
    $uObject = $this->get('redback');
```