# U2 Web Development Environment (RedBack) for PHP
[![Build Status](https://travis-ci.org/gheydon/redback.png)](https://travis-ci.org/gheydon/redback)
Allows native communitcation with RocketSoftware’s U2 database Enviroments using the U2 Web Development Environment, and also providing methods which allow PHP to make greater use of the native U2 dynamic arrays.
## U2 Web Development Environment (RedBack) 4
Full support for Web DE 4.2.16+ in native PHP.
## U2 Web Development Environment (RedBack) 5
Currently not supported. This is being currently worked on, and any support will be much appreciated.

The major problem with version 5 is that it is no longer running on the old Redback Scheduler, but instead running over UniObjects, so to talk to the the U2 Web DE we need to be able to talk UniObjects. I have started decoding the protocole, and starting an implementation a functioning PHP version, but this will take time.

However because I needed to build the uarray libraries to work with U2 Dynamic Arrays the actual handling of the raw data is almost done. There are areas which will need to be improved, such needing to be able to handle the internal data formats like dates and numbers which will mean that I will need to implement OCONV and ICONV methods to the uArray object.