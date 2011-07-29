CREATE TABLE IF NOT EXISTS incidents (
  hash varchar(32) NOT NULL,
  subject varchar(255) NOT NULL,
  data longblob,
  occurrences int(11) NOT NULL DEFAULT 1,
  created_at timestamp NOT NULL DEFAULT 0,
  last_seen_at timestamp NOT NULL DEFAULT 0,
  resolved_at timestamp NULL DEFAULT NULL,
  PRIMARY KEY (hash)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ;