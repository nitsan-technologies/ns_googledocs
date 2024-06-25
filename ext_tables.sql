#
# Table structure for table 'be_users'
#
CREATE TABLE be_users (
	client_id varchar(255) DEFAULT '' NOT NULL,
	client_secret varchar(255) DEFAULT '' NOT NULL,
	refresh_token varchar(255) DEFAULT '' NOT NULL,	
	import_type varchar(255) DEFAULT '1' NOT NULL,
);
