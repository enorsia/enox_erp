-- =============================================
-- EnoxTracker — ClickHouse Database Setup
-- =============================================
-- Run this file against your ClickHouse instance
-- to create / update the analytics schema.
-- =============================================

-- Create Database
CREATE DATABASE IF NOT EXISTS enox_tracker;

-- =============================================
-- 1. Raw Events Table (Main event store)
-- =============================================
CREATE TABLE IF NOT EXISTS enox_tracker.events
(
    event_id UUID DEFAULT generateUUIDv4(),
    user_id String DEFAULT '',
    anonymous_id String DEFAULT '',
    session_id String DEFAULT '',
    event_name String,
    product_id String DEFAULT '',
    product_name String DEFAULT '',
    sku String DEFAULT '',
    variant_color String DEFAULT '',
    variant_size String DEFAULT '',
    category String DEFAULT '',
    price Float64 DEFAULT 0,
    quantity UInt32 DEFAULT 0,
    currency String DEFAULT 'GBP',
    page_url String DEFAULT '',
    page_title String DEFAULT '',
    page_path String DEFAULT '',
    referrer String DEFAULT '',
    utm_source String DEFAULT '',
    utm_medium String DEFAULT '',
    utm_campaign String DEFAULT '',
    utm_term String DEFAULT '',
    utm_content String DEFAULT '',
    device_type String DEFAULT '',
    browser String DEFAULT '',
    os String DEFAULT '',
    screen_resolution String DEFAULT '',
    ip_address String DEFAULT '',
    country String DEFAULT '',
    properties String DEFAULT '{}',
    -- Time the user had been actively on the current page when this event fired (seconds).
    -- Paused while the tab is hidden or the window is blurred; resumes on focus/return.
    duration_seconds Float64 DEFAULT 0,
    -- Total wall-clock seconds since page entry (including background time)
    total_seconds_on_page Float64 DEFAULT 0,
    event_timestamp DateTime64(3) DEFAULT now64(3),
    received_at DateTime64(3) DEFAULT now64(3),
    created_date Date DEFAULT toDate(now())
)
ENGINE = MergeTree()
PARTITION BY toYYYYMM(created_date)
ORDER BY (event_name, session_id, event_timestamp)
TTL created_date + INTERVAL 2 YEAR;

-- =============================================
-- 1a. Live migration — add duration columns to an existing events table
--     These are no-ops when the table was freshly created above.
-- =============================================
ALTER TABLE enox_tracker.events
    ADD COLUMN IF NOT EXISTS duration_seconds Float64 DEFAULT 0,
    ADD COLUMN IF NOT EXISTS total_seconds_on_page Float64 DEFAULT 0;

-- =============================================
-- 2. Sessions Table
-- =============================================
CREATE TABLE IF NOT EXISTS enox_tracker.sessions
(
    session_id String,
    anonymous_id String DEFAULT '',
    user_id String DEFAULT '',
    device_type String DEFAULT '',
    browser String DEFAULT '',
    os String DEFAULT '',
    screen_resolution String DEFAULT '',
    ip_address String DEFAULT '',
    country String DEFAULT '',
    referrer String DEFAULT '',
    utm_source String DEFAULT '',
    utm_medium String DEFAULT '',
    utm_campaign String DEFAULT '',
    landing_page String DEFAULT '',
    is_new_user UInt8 DEFAULT 1,
    page_count UInt32 DEFAULT 0,
    event_count UInt32 DEFAULT 0,
    start_time DateTime64(3) DEFAULT now64(3),
    end_time DateTime64(3) DEFAULT now64(3),
    duration_seconds UInt32 DEFAULT 0,
    created_date Date DEFAULT toDate(now())
)
ENGINE = ReplacingMergeTree(end_time)
PARTITION BY toYYYYMM(created_date)
ORDER BY (session_id, anonymous_id);

-- =============================================
-- 3. Product Daily Stats (Aggregated)
-- =============================================
CREATE TABLE IF NOT EXISTS enox_tracker.product_daily_stats
(
    stat_date Date,
    product_id String,
    product_name String DEFAULT '',
    sku String DEFAULT '',
    variant_color String DEFAULT '',
    views UInt64 DEFAULT 0,
    unique_views UInt64 DEFAULT 0,
    add_to_cart UInt64 DEFAULT 0,
    add_to_wishlist UInt64 DEFAULT 0,
    removed_from_cart UInt64 DEFAULT 0,
    orders UInt64 DEFAULT 0,
    revenue Float64 DEFAULT 0,
    avg_time_on_page Float64 DEFAULT 0,
    variant_selections UInt64 DEFAULT 0,
    image_interactions UInt64 DEFAULT 0,
    scroll_depth_avg Float64 DEFAULT 0
)
ENGINE = SummingMergeTree()
PARTITION BY toYYYYMM(stat_date)
ORDER BY (stat_date, product_id, variant_color);

-- =============================================
-- 4. Page Daily Stats (Aggregated)
-- =============================================
CREATE TABLE IF NOT EXISTS enox_tracker.page_daily_stats
(
    stat_date Date,
    page_path String,
    page_title String DEFAULT '',
    page_views UInt64 DEFAULT 0,
    unique_visitors UInt64 DEFAULT 0,
    avg_time_on_page Float64 DEFAULT 0,
    bounce_count UInt64 DEFAULT 0,
    exit_count UInt64 DEFAULT 0
)
ENGINE = SummingMergeTree()
PARTITION BY toYYYYMM(stat_date)
ORDER BY (stat_date, page_path);

-- =============================================
-- 5. Element Interactions (every click / rage click)
-- =============================================
CREATE TABLE IF NOT EXISTS enox_tracker.element_interactions
(
    event_id UUID DEFAULT generateUUIDv4(),
    session_id String DEFAULT '',
    anonymous_id String DEFAULT '',
    user_id String DEFAULT '',
    element_tag String DEFAULT '',
    element_id String DEFAULT '',
    element_classes String DEFAULT '',
    element_text String DEFAULT '',
    data_track String DEFAULT '',
    click_x UInt16 DEFAULT 0,
    click_y UInt16 DEFAULT 0,
    page_path String DEFAULT '',
    page_url String DEFAULT '',
    is_rage_click UInt8 DEFAULT 0,
    rage_click_count UInt8 DEFAULT 0,
    ip_address String DEFAULT '',
    event_timestamp DateTime64(3) DEFAULT now64(3),
    created_date Date DEFAULT toDate(now())
)
ENGINE = MergeTree()
PARTITION BY toYYYYMM(created_date)
ORDER BY (session_id, event_timestamp)
TTL created_date + INTERVAL 2 YEAR;

-- =============================================
-- 6. Section Visibility (time spent viewing sections)
-- =============================================
CREATE TABLE IF NOT EXISTS enox_tracker.section_visibility
(
    event_id UUID DEFAULT generateUUIDv4(),
    session_id String DEFAULT '',
    anonymous_id String DEFAULT '',
    user_id String DEFAULT '',
    section_id String DEFAULT '',
    section_label String DEFAULT '',
    visible_seconds Float64 DEFAULT 0,
    visible_percent UInt8 DEFAULT 0,
    page_path String DEFAULT '',
    ip_address String DEFAULT '',
    event_timestamp DateTime64(3) DEFAULT now64(3),
    created_date Date DEFAULT toDate(now())
)
ENGINE = MergeTree()
PARTITION BY toYYYYMM(created_date)
ORDER BY (session_id, section_id, event_timestamp)
TTL created_date + INTERVAL 2 YEAR;

-- =============================================
-- 7. Accordion Tracking (open / close / duration)
-- =============================================
CREATE TABLE IF NOT EXISTS enox_tracker.accordion_tracking
(
    event_id UUID DEFAULT generateUUIDv4(),
    session_id String DEFAULT '',
    anonymous_id String DEFAULT '',
    user_id String DEFAULT '',
    product_id String DEFAULT '',
    accordion_name String DEFAULT '',
    action String DEFAULT '',
    duration_seconds Float64 DEFAULT 0,
    page_path String DEFAULT '',
    ip_address String DEFAULT '',
    event_timestamp DateTime64(3) DEFAULT now64(3),
    created_date Date DEFAULT toDate(now())
)
ENGINE = MergeTree()
PARTITION BY toYYYYMM(created_date)
ORDER BY (session_id, accordion_name, event_timestamp)
TTL created_date + INTERVAL 2 YEAR;

-- =============================================
-- 8. Page Transitions (from → to with time)
-- =============================================
CREATE TABLE IF NOT EXISTS enox_tracker.page_transitions
(
    event_id UUID DEFAULT generateUUIDv4(),
    session_id String DEFAULT '',
    anonymous_id String DEFAULT '',
    user_id String DEFAULT '',
    from_path String DEFAULT '',
    from_title String DEFAULT '',
    to_path String DEFAULT '',
    time_on_from_seconds UInt32 DEFAULT 0,
    ip_address String DEFAULT '',
    event_timestamp DateTime64(3) DEFAULT now64(3),
    created_date Date DEFAULT toDate(now())
)
ENGINE = MergeTree()
PARTITION BY toYYYYMM(created_date)
ORDER BY (session_id, event_timestamp)
TTL created_date + INTERVAL 2 YEAR;

