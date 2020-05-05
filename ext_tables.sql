#
# Table structure for table 'be_users'
#
CREATE TABLE be_users (
	client_id varchar(255) DEFAULT '' NOT NULL,
	client_secret varchar(255) DEFAULT '' NOT NULL,
	refresh_token varchar(255) DEFAULT '' NOT NULL,	
	import_type varchar(255) DEFAULT '1' NOT NULL,
);

#
# Table structure for table 'tx_nsgoogledocs_domain_model_apidata'
#
CREATE TABLE tx_nsgoogledocs_domain_model_apidata (
   id int(11) NOT NULL auto_increment,
   extension_key varchar(255) DEFAULT '',
   googleDocsSetupWizard text,
   premuim_extension_html text,
   support_html text,
   footer_html text,
   last_update date,

   PRIMARY KEY (id)
);