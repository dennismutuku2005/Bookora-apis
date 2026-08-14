-- Bookora Platform - MySQL Database Schema
-- Simplified schema based on Bookora app features
-- Stores: Users, Books/Listings, Claims, Messages, Favorites, Notifications

-- ============================================================
-- USERS TABLE - User profiles and authentication
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id VARCHAR(255) PRIMARY KEY,
    firstName VARCHAR(100) NOT NULL,
    lastName VARCHAR(100) NOT NULL,
    username VARCHAR(255) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password_hash VARCHAR(255),
    reset_token_hash VARCHAR(64) NULL,
    reset_token_expires TIMESTAMP NULL,
    avatarUrl VARCHAR(500),
    bio TEXT,
    memberSince TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    rating DECIMAL(3, 2) DEFAULT 0.00,
    booksPosted INT DEFAULT 0,
    booksShared INT DEFAULT 0,
    favoritesCount INT DEFAULT 0,
    shareContactByEmail BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_reset_token_hash (reset_token_hash),
    INDEX idx_rating (rating)
);

-- ============================================================
-- CATEGORIES - Book categories
-- ============================================================
CREATE TABLE IF NOT EXISTS categories (
    id VARCHAR(255) PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_title (title)
);

-- ============================================================
-- BOOKS - Book listings/posts
-- ============================================================
CREATE TABLE IF NOT EXISTS books (
    id VARCHAR(255) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    category VARCHAR(100),
    `condition` ENUM('NEW', 'LIKE_NEW', 'GOOD', 'FAIR') DEFAULT 'GOOD',
    location VARCHAR(255),
    postedTimestamp BIGINT NOT NULL,
    coverUrl VARCHAR(500),
    `listingType` ENUM('EXCHANGE', 'GIVEAWAY') NOT NULL DEFAULT 'GIVEAWAY',
    description LONGTEXT,
    ownerId VARCHAR(255) NOT NULL,
    rating DECIMAL(3, 2) DEFAULT 0.00,
    coverColor VARCHAR(20),
    -- location column holds human-readable location; prefer using postedTimestamp/created_at for timing
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ownerId) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_ownerId (ownerId),
    INDEX idx_title (title),
    INDEX idx_author (author),
    INDEX idx_condition (`condition`),
    INDEX idx_listingType (`listingType`),
    INDEX idx_postedTimestamp (postedTimestamp),
    FULLTEXT INDEX ft_search (title, author, description)
);

-- ============================================================
-- CLAIM REQUESTS - User claims/requests for books
-- ============================================================
CREATE TABLE IF NOT EXISTS claim_requests (
    id VARCHAR(255) PRIMARY KEY,
    bookId VARCHAR(255) NOT NULL,
    bookTitle VARCHAR(255),
    claimerId VARCHAR(255) NOT NULL,
    claimerName VARCHAR(255),
    claimerEmail VARCHAR(255),
    claimerPhone VARCHAR(20),
    ownerId VARCHAR(255) NOT NULL,
    ownerName VARCHAR(255),
    `status` ENUM('PENDING', 'ACCEPTED', 'CONFIRMED_CLAIMER', 'CONFIRMED_OWNER', 'COMPLETED', 'REJECTED') DEFAULT 'PENDING',
    timestamp BIGINT NOT NULL,
    confirmedByClaimer BOOLEAN DEFAULT FALSE,
    confirmedByOwner BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (bookId) REFERENCES books(id) ON DELETE CASCADE,
    FOREIGN KEY (claimerId) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (ownerId) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_bookId (bookId),
    INDEX idx_claimerId (claimerId),
    INDEX idx_ownerId (ownerId),
    INDEX idx_status (`status`),
    INDEX idx_timestamp (timestamp)
);

-- ============================================================
-- CHAT CONVERSATIONS - Message threads between users
-- ============================================================
CREATE TABLE IF NOT EXISTS chat_conversations (
    id VARCHAR(255) PRIMARY KEY,
    participant1Id VARCHAR(255) NOT NULL,
    participant2Id VARCHAR(255) NOT NULL,
    participant1Name VARCHAR(255),
    participant2Name VARCHAR(255),
    lastMessage LONGTEXT,
    lastTimestamp BIGINT,
    bookId VARCHAR(255),
    bookTitle VARCHAR(255),
    unreadCount INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (participant1Id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (participant2Id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (bookId) REFERENCES books(id) ON DELETE SET NULL,
    INDEX idx_participant1Id (participant1Id),
    INDEX idx_participant2Id (participant2Id),
    INDEX idx_lastTimestamp (lastTimestamp),
    INDEX idx_bookId (bookId)
);

-- ============================================================
-- MESSAGES - Individual messages in conversations
-- ============================================================
CREATE TABLE IF NOT EXISTS messages (
    id VARCHAR(255) PRIMARY KEY,
    conversationId VARCHAR(255) NOT NULL,
    senderId VARCHAR(255) NOT NULL,
    senderName VARCHAR(255),
    text LONGTEXT,
    timestamp BIGINT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversationId) REFERENCES chat_conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (senderId) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_conversationId (conversationId),
    INDEX idx_senderId (senderId),
    INDEX idx_timestamp (timestamp),
    INDEX idx_is_read (is_read)
);

-- ============================================================
-- FAVORITES - Saved/Favorited books
-- ============================================================
CREATE TABLE IF NOT EXISTS favorites (
    id VARCHAR(255) PRIMARY KEY,
    userId VARCHAR(255) NOT NULL,
    bookId VARCHAR(255) NOT NULL,
    timestamp BIGINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (userId) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (bookId) REFERENCES books(id) ON DELETE CASCADE,
    INDEX idx_userId (userId),
    INDEX idx_bookId (bookId),
    UNIQUE KEY unique_favorite (userId, bookId)
);

-- ============================================================
-- NOTIFICATIONS - User notifications
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
    id VARCHAR(255) PRIMARY KEY,
    userId VARCHAR(255) NOT NULL,
    title VARCHAR(255),
    subtitle TEXT,
    timeAgo VARCHAR(50),
    is_read BOOLEAN DEFAULT FALSE,
    type VARCHAR(50) DEFAULT 'notification',
    conversationId VARCHAR(255),
    senderId VARCHAR(255),
    bookId VARCHAR(255),
    claimRequestId VARCHAR(255),
    timestamp BIGINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (userId) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (senderId) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (bookId) REFERENCES books(id) ON DELETE SET NULL,
    FOREIGN KEY (conversationId) REFERENCES chat_conversations(id) ON DELETE SET NULL,
    FOREIGN KEY (claimRequestId) REFERENCES claim_requests(id) ON DELETE SET NULL,
    INDEX idx_userId (userId),
    INDEX idx_is_read (is_read),
    INDEX idx_timestamp (timestamp),
    INDEX idx_type (type)
);
