CREATE TABLE blog_entries (
id INT NOT NULL,
key_id INT NOT NULL,
title VARCHAR( 60 ) NOT NULL ,
entry TEXT NOT NULL,
author_id INT NOT NULL default '0',
author varchar(50) NOT NULL default '',
create_date INT NOT NULL ,
allow_comments SMALLINT NOT NULL default '0',
approved INT NOT NULL default '0',
PRIMARY KEY ( id )
);

CREATE INDEX blogentries_idx on blog_entries(key_id);
