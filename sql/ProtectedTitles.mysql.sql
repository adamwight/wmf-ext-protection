-- Protected titles - nonexistent pages that have been protected
CREATE TABLE /*_*/protected_titles (
  pt_namespace int NOT NULL,
  pt_title varchar(255) binary NOT NULL,
  pt_user int unsigned NOT NULL,
  pt_reason tinyblob,
  pt_timestamp binary(14) NOT NULL,
  pt_expiry varbinary(14) NOT NULL default '',
  pt_create_perm varbinary(60) NOT NULL
) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/pt_namespace_title ON /*_*/protected_titles (pt_namespace,pt_title);
CREATE INDEX /*i*/pt_timestamp ON /*_*/protected_titles (pt_timestamp);
