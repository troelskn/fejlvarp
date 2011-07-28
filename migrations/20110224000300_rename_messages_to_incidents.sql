ALTER TABLE messages CHANGE COLUMN incidents occurrences INTEGER NOT NULL DEFAULT 1 ;
RENAME TABLE messages TO incidents ;