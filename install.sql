CREATE TABLE IF NOT EXISTS incidents (
  hash varchar(32) NOT NULL,
  subject varchar(255) NOT NULL,
  data longblob,
  occurrences int(11) NOT NULL DEFAULT 1,
  created_at datetime NOT NULL,
  last_seen_at datetime NOT NULL,
  resolved_at datetime NULL DEFAULT NULL,
  PRIMARY KEY (hash)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ;