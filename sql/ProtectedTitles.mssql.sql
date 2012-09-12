-- Protected titles - nonexistent pages that have been protected
CREATE TABLE /*$wgDBprefix*/protected_titles (
  pt_namespace int NOT NULL,
  pt_title NVARCHAR(255) NOT NULL,
  pt_user int NOT NULL,
  pt_reason NVARCHAR(3555),
  pt_timestamp DATETIME NOT NULL,
  pt_expiry DATETIME NOT NULL default '',
  pt_create_perm NVARCHAR(60) NOT NULL,
  PRIMARY KEY (pt_namespace,pt_title),
);
CREATE INDEX /*$wgDBprefix*/pt_timestamp   ON /*$wgDBprefix*/protected_titles(pt_timestamp);
