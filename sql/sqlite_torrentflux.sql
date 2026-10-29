-- TorrentFlux NX SQLite schema and default settings.
-- installer.php loads this into a new database, then fills in this server's paths
-- (path, btphpbin, btshowmetainfo, btmakemetafile, tfQManager, pythonCmd) and adds the admin account.

-- Links table
CREATE TABLE tf_links (
  lid INTEGER PRIMARY KEY AUTOINCREMENT,
  url varchar(255) NOT NULL default '',
  sitename varchar(255) NOT NULL default 'Old Link',
  sort_order INTEGER default 0
);

-- Log table
CREATE TABLE tf_log (
  cid INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id varchar(32) NOT NULL default '',
  file varchar(200) NOT NULL default '',
  action varchar(200) NOT NULL default '',
  ip varchar(45) NOT NULL default '',
  ip_resolved varchar(200) NOT NULL default '',
  user_agent varchar(200) NOT NULL default '',
  time varchar(14) NOT NULL default '0'
);

-- Messages table
CREATE TABLE tf_messages (
  mid INTEGER PRIMARY KEY AUTOINCREMENT,
  to_user varchar(32) NOT NULL default '',
  from_user varchar(32) NOT NULL default '',
  message text,
  IsNew INTEGER default NULL,
  ip varchar(45) NOT NULL default '',
  time varchar(14) NOT NULL default '0',
  force_read INTEGER default 0
);

-- RSS sources table
CREATE TABLE tf_rss (
  rid INTEGER PRIMARY KEY AUTOINCREMENT,
  url varchar(255) NOT NULL default ''
);

-- Settings table (key-value store)
CREATE TABLE tf_settings (
  tf_key varchar(255) NOT NULL,
  tf_value text NOT NULL,
  PRIMARY KEY (tf_key)
);

-- Users table (password holds a password_hash() bcrypt hash)
CREATE TABLE tf_users (
  uid INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id varchar(32) NOT NULL default '',
  password varchar(255) NOT NULL default '',
  hits INTEGER NOT NULL default 0,
  last_visit varchar(14) NOT NULL default '0',
  time_created varchar(14) NOT NULL default '0',
  user_level INTEGER NOT NULL default 0,
  hide_offline INTEGER NOT NULL default 0,
  theme varchar(100) NOT NULL default 'sage',
  language_file varchar(60) default 'lang-english.php'
);

-- Cookies table
CREATE TABLE tf_cookies (
  cid INTEGER PRIMARY KEY AUTOINCREMENT,
  uid INTEGER NOT NULL,
  host varchar(255) default NULL,
  data varchar(255) default NULL
);

-- Default settings (the paths are set by installer.php)
INSERT INTO tf_settings VALUES ('path', '');
INSERT INTO tf_settings VALUES ('btphpbin', '');
INSERT INTO tf_settings VALUES ('btshowmetainfo', '');
INSERT INTO tf_settings VALUES ('btmakemetafile', '');
INSERT INTO tf_settings VALUES ('tfQManager', '');
INSERT INTO tf_settings VALUES ('pythonCmd', '');
INSERT INTO tf_settings VALUES ('advanced_start', '1');
INSERT INTO tf_settings VALUES ('max_upload_rate', '10');
INSERT INTO tf_settings VALUES ('max_download_rate', '0');
INSERT INTO tf_settings VALUES ('max_uploads', '4');
INSERT INTO tf_settings VALUES ('minport', '49160');
INSERT INTO tf_settings VALUES ('maxport', '49300');
INSERT INTO tf_settings VALUES ('rerequest_interval', '1800');
INSERT INTO tf_settings VALUES ('cmd_options', '');
INSERT INTO tf_settings VALUES ('enable_search', '0');
INSERT INTO tf_settings VALUES ('enable_file_download', '1');
INSERT INTO tf_settings VALUES ('enable_view_nfo', '1');
INSERT INTO tf_settings VALUES ('package_type', 'zip');
INSERT INTO tf_settings VALUES ('show_server_load', '1');
INSERT INTO tf_settings VALUES ('loadavg_path', '/proc/loadavg');
INSERT INTO tf_settings VALUES ('days_to_keep', '30');
INSERT INTO tf_settings VALUES ('minutes_to_keep', '3');
INSERT INTO tf_settings VALUES ('rss_cache_min', '20');
INSERT INTO tf_settings VALUES ('page_refresh', '60');
INSERT INTO tf_settings VALUES ('default_theme', 'sage');
INSERT INTO tf_settings VALUES ('default_language', 'lang-english.php');
INSERT INTO tf_settings VALUES ('debug_sql', '0');
INSERT INTO tf_settings VALUES ('torrent_dies_when_done', 'False');
INSERT INTO tf_settings VALUES ('sharekill', '150');
INSERT INTO tf_settings VALUES ('AllowQueing', '0');
INSERT INTO tf_settings VALUES ('maxServerThreads', '5');
INSERT INTO tf_settings VALUES ('maxUserThreads', '2');
INSERT INTO tf_settings VALUES ('sleepInterval', '10');
INSERT INTO tf_settings VALUES ('debugTorrents', '0');
INSERT INTO tf_settings VALUES ('enable_maketorrent', '1');
INSERT INTO tf_settings VALUES ('enable_torrent_download', '1');
INSERT INTO tf_settings VALUES ('enable_file_priority', '1');
INSERT INTO tf_settings VALUES ('security_code', '0');
INSERT INTO tf_settings VALUES ('crypto_allowed', '1');
INSERT INTO tf_settings VALUES ('crypto_only', '1');
INSERT INTO tf_settings VALUES ('crypto_stealth', '0');
