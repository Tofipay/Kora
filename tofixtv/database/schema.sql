-- ============================================================
-- ALOKA Live — optional MySQL schema
-- ============================================================
-- The platform runs WITHOUT a database by default: all sports data
-- comes from the upstream scores API and is cached on disk, and all
-- admin settings live in storage/settings/*.json.
--
-- Use this schema when you want to scale beyond JSON files
-- (multi-server deployments, millions of push tokens, SQL analytics).
-- Each table maps 1:1 to a JSON settings group so migration is a
-- straight import.

CREATE DATABASE IF NOT EXISTS aloka_live
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE aloka_live;

-- storage/settings/*.json  →  key/value settings
CREATE TABLE settings (
  `group`     VARCHAR(40)  NOT NULL,
  `data`      JSON         NOT NULL,
  updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`group`)
) ENGINE=InnoDB;

-- storage/settings/push_tokens.json  →  FCM device tokens
CREATE TABLE push_tokens (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token       VARCHAR(4096) NOT NULL,
  token_hash  CHAR(40) AS (SHA1(token)) STORED,
  topics      JSON NULL,
  lang        ENUM('ar','en') NOT NULL DEFAULT 'ar',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen   TIMESTAMP NULL,
  UNIQUE KEY uq_token (token_hash)
) ENGINE=InnoDB;

-- storage/settings/newsletter.json  →  newsletter signups
CREATE TABLE newsletter_subscribers (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email       VARCHAR(190) NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  confirmed   TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_email (email)
) ENGINE=InnoDB;

-- storage/settings/analytics.json  →  daily page-view counters
CREATE TABLE analytics_daily (
  day         DATE NOT NULL,
  page_type   VARCHAR(24) NOT NULL,     -- home | matches | match | news | article | league | team | player | ...
  hits        BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (day, page_type)
) ENGINE=InnoDB;

-- API response cache (replaces storage/cache/api when using SQL)
CREATE TABLE api_cache (
  cache_key   CHAR(32) NOT NULL,        -- md5(url)
  url         TEXT NOT NULL,
  payload     LONGTEXT NOT NULL,        -- JSON body
  fetched_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (cache_key),
  KEY idx_fetched (fetched_at)
) ENGINE=InnoDB;

-- Editorial layer (optional): publish your own articles alongside API news
CREATE TABLE articles (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug        VARCHAR(190) NOT NULL,
  lang        ENUM('ar','en') NOT NULL DEFAULT 'ar',
  title       VARCHAR(255) NOT NULL,
  excerpt     TEXT NULL,
  body_html   MEDIUMTEXT NULL,
  cover_image VARCHAR(255) NULL,
  status      ENUM('draft','published') NOT NULL DEFAULT 'draft',
  published_at TIMESTAMP NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_slug_lang (slug, lang),
  KEY idx_status (status, published_at)
) ENGINE=InnoDB;

-- Notification log (what the cron worker sent)
CREATE TABLE notifications_log (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_type  VARCHAR(24) NOT NULL,     -- match_start | goal | red_card | half_time | full_time | news | manual
  match_id    BIGINT UNSIGNED NULL,
  title       VARCHAR(255) NOT NULL,
  body        VARCHAR(500) NULL,
  sent_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fcm_status  VARCHAR(40) NULL,
  KEY idx_match (match_id),
  KEY idx_sent (sent_at)
) ENGINE=InnoDB;
