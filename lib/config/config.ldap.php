<?php
	// The hostname of ip adress of the ldap server.
	define('LDAP_HOST', '');
	
	// The port where the ldap service is running on.
	define('LDAP_PORT', '');
	
	// Whether to use an encrypted TLS connection or not. 
	define('LDAP_TLS', true);
	
	// This is the Distinguished Name, locating the user information.
	define('LDAP_BASE_DN', '');
	
	// The attribute containing the uid information.
	define('LDAP_UID', '');
	
	// A user specified filter, for example objectClass=posixGroup would
	// result in the use of (&(uid=$username)(objectClass=posixGroup)) .
	define('LDAP_FILTER', '');
	
	// Optionally bind with a defined user. Otherwise anonymous bind will be used.
	define('LDAP_BIND_DN', '');
	
	// Password for the optional bind user.
	define('LDAP_PWD', '');
?>