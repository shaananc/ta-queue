<?php
define("SRV_HOSTNAME", "");

define("LDAP_SERVERS", []);
define("LDAP_DOMAIN", "");
define("BIND_USER",   "");
define("BIND_PASSWD", "");
define("BASE_OU",     "");

define("SQL_SERVER",  "db");
define("SQL_USER",    "root");
define("SQL_PASSWD",  "ghXr74@439-M");
define("DATABASE",    "taqueue");

define("HELP_EMAIL",  "cohneys@unimelb.edu.au");

//Auth must be LDAP or CAS
define("AUTH", "LDAP");
if (AUTH == "CAS") {
  $phpcas_path = '';
  $cas_host = '';
  $cas_context = '';
  $cas_port = 443;
  $cas_server_ca_cert_path = '';
}
