CREATE TABLE users_config (
  default_group int NOT NULL default '0',
  default_authorization smallint NOT NULL default '1'
);

INSERT INTO users_config VALUES (0, 1);

CREATE TABLE users_groups (
 id INT NOT NULL PRIMARY KEY,
 active SMALLINT NOT NULL,
 name CHAR(50) NOT NULL,
 user_id INT NOT NULL
 );

CREATE TABLE users_members (
 group_id INT NOT NULL,
 member_id INT NOT NULL
 );

CREATE TABLE users (
  id int NOT NULL default '0',
  last_logged int default NULL,
  log_count int NOT NULL default '0',
  authorize smallint NOT NULL default '0',
  created int NOT NULL default '0',
  updated int NOT NULL default '0',
  active smallint NOT NULL default '0',
  approved smallint NOT NULL default '0',
  username varchar(30) NOT NULL default '',
  display_name varchar(30) NOT NULL default '',
  email varchar(100) default NULL,
  deity smallint NOT NULL default '0',
  PRIMARY KEY  (id)
);

CREATE TABLE user_authorization (
  username varchar(40) NOT NULL default '',
  password CHAR(32) NOT NULL default ''
);

CREATE TABLE users_auth_scripts (
  id smallint NOT NULL default '0',
  display_name varchar(40) NOT NULL default '',
  filename varchar(40) NOT NULL default '',
  PRIMARY KEY (id)
);

CREATE TABLE users_my_page_mods (
  mod_title varchar(40) NOT NULL default ''
);
