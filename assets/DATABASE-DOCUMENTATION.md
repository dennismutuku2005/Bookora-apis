# Bookora Platform - Database Schema Documentation

## Overview

This document outlines the MySQL database schema for the Bookora community book sharing platform. Based on the app's actual features, the schema stores:
- User profiles and authentication
- Book listings (EXCHANGE or GIVEAWAY)
- Claim requests for books
- Real-time messaging between users
- Favorites/saved books
- Notifications

---

## Design Principles

1. **Simplified Structure** - Single users table (no separate profiles table)
2. **String IDs** - Primary keys are VARCHAR(255) for compatibility with app IDs
3. **Timestamp Support** - BIGINT timestamps for app compatibility
4. **Minimal Data** - Only tables and fields used by the app
5. **Performance Indexed** - Strategic indexing for common queries
6. **Referential Integrity** - Foreign keys maintain data consistency

---

## Tables

### 1. **users** - User Accounts & Profiles
Stores all user information in a single table.

| Column | Type | Notes |
|--------|------|-------|
| id | VARCHAR(255) | Primary key, Firebase UID |
| firstName | VARCHAR(100) | First name |
| lastName | VARCHAR(100) | Last name |
| username | VARCHAR(255) | Unique username |
| email | VARCHAR(255) | Unique email |
| phone | VARCHAR(20) | Phone number |
| password_hash | VARCHAR(255) | Hashed password (if not using Firebase Auth) |
| avatarUrl | VARCHAR(500) | Profile picture URL |
| bio | TEXT | User biography |
| memberSince | TIMESTAMP | Account creation date |
| rating | DECIMAL(3, 2) | Average rating (0-5) |
| booksPosted | INT | Count of books posted |
| booksShared | INT | Count of successful exchanges |
| favoritesCount | INT | Count of favorited books |
| shareContactByEmail | BOOLEAN | Privacy setting for email sharing |
| is_active | BOOLEAN | Account status |
| last_login | TIMESTAMP | Last login timestamp |
| updated_at | TIMESTAMP | Last profile update |

**Example User:**
```json
{
  "id": "firebase_uid_123",
  "firstName": "John",
  "lastName": "Doe",
  "username": "johndoe",
  "email": "john@example.com",
  "phone": "+254712345678",
  "avatarUrl": "https://storage.googleapis.com/...",
  "bio": "Book lover from Nairobi",
  "rating": 4.8,
  "booksPosted": 15,
  "booksShared": 12,
  "favoritesCount": 23
}
```

---

### 2. **categories** - Book Categories
Reference table for book categories.

| Column | Type | Notes |
|--------|------|-------|
| id | VARCHAR(255) | Category ID |
| title | VARCHAR(100) | Category name |

**Example Categories:**
- Fiction
- Non-Fiction
- Science
- Technology
- Self-Help
- History

---

### 3. **books** - Book Listings/Posts
Stores all book listings posted by users.

| Column | Type | Notes |
|--------|------|-------|
| id | VARCHAR(255) | Unique listing ID |
| title | VARCHAR(255) | Book title |
| author | VARCHAR(255) | Author name |
| category | VARCHAR(100) | Book category |
| condition | ENUM | NEW, LIKE_NEW, GOOD, FAIR |
| location | VARCHAR(255) | Location of book |
| postedDate | VARCHAR(50) | Human-readable date |
| postedTimestamp | BIGINT | Unix timestamp |
| coverUrl | VARCHAR(500) | Book cover image URL |
| listingType | ENUM | EXCHANGE or GIVEAWAY |
| description | LONGTEXT | Book description |
| ownerId | VARCHAR(255) | User ID of poster |
| ownerUsername | VARCHAR(255) | Username of poster |
| rating | DECIMAL(3, 2) | Book rating |
| distance | VARCHAR(50) | Distance from user |
| coverColor | VARCHAR(20) | Dominant cover color (hex) |
| created_at | TIMESTAMP | Record creation |
| updated_at | TIMESTAMP | Last update |

**Example Book Listing:**
```json
{
  "id": "book_123",
  "title": "The Lean Startup",
  "author": "Eric Ries",
  "category": "Business",
  "condition": "GOOD",
  "location": "Nairobi, Kenya",
  "postedTimestamp": 1692345600,
  "coverUrl": "https://...",
  "listingType": "EXCHANGE",
  "description": "Lightly used, excellent condition. Looking for science fiction novels",
  "ownerId": "user_456",
  "ownerUsername": "johndoe"
}
```

---

### 4. **claim_requests** - Book Claims/Requests
Tracks when users claim/request a book listing.

| Column | Type | Notes |
|--------|------|-------|
| id | VARCHAR(255) | Claim ID |
| bookId | VARCHAR(255) | Book being claimed |
| bookTitle | VARCHAR(255) | Title snapshot |
| claimerId | VARCHAR(255) | User making claim |
| claimerName | VARCHAR(255) | Claimer's name |
| claimerEmail | VARCHAR(255) | Claimer's email |
| claimerPhone | VARCHAR(20) | Claimer's phone |
| ownerId | VARCHAR(255) | Book owner |
| ownerName | VARCHAR(255) | Owner's name |
| status | ENUM | PENDING, ACCEPTED, CONFIRMED_CLAIMER, CONFIRMED_OWNER, COMPLETED, REJECTED |
| timestamp | BIGINT | Request timestamp |
| confirmedByClaimer | BOOLEAN | Claimer confirmed exchange |
| confirmedByOwner | BOOLEAN | Owner confirmed exchange |
| created_at | TIMESTAMP | Record creation |
| updated_at | TIMESTAMP | Last update |

**Claim Status Flow:**
- PENDING → User requests book
- ACCEPTED → Owner accepts the claim
- CONFIRMED_CLAIMER → Claimer confirms the exchange
- CONFIRMED_OWNER → Owner confirms the exchange
- COMPLETED → Exchange completed
- REJECTED → Owner rejected the claim

**Example Claim:**
```json
{
  "id": "claim_789",
  "bookId": "book_123",
  "claimerId": "user_789",
  "claimerName": "Jane Smith",
  "ownerId": "user_456",
  "status": "ACCEPTED",
  "timestamp": 1692432000
}
```

---

### 5. **chat_conversations** - Message Threads
Tracks conversations between users about book exchanges.

| Column | Type | Notes |
|--------|------|-------|
| id | VARCHAR(255) | Conversation ID |
| participant1Id | VARCHAR(255) | First user |
| participant2Id | VARCHAR(255) | Second user |
| participant1Name | VARCHAR(255) | First user's name |
| participant2Name | VARCHAR(255) | Second user's name |
| lastMessage | LONGTEXT | Last message text |
| lastTimestamp | BIGINT | Last message timestamp |
| bookId | VARCHAR(255) | Related book (if any) |
| bookTitle | VARCHAR(255) | Related book title |
| unreadCount | INT | Count of unread messages |
| created_at | TIMESTAMP | Conversation created |
| updated_at | TIMESTAMP | Last activity |

**Example Conversation:**
```json
{
  "id": "conv_123",
  "participant1Id": "user_456",
  "participant1Name": "John Doe",
  "participant2Id": "user_789",
  "participant2Name": "Jane Smith",
  "lastMessage": "Can we meet next Friday?",
  "lastTimestamp": 1692518400,
  "bookId": "book_123",
  "bookTitle": "The Lean Startup"
}
```

---

### 6. **messages** - Individual Messages
Stores all messages in conversations.

| Column | Type | Notes |
|--------|------|-------|
| id | VARCHAR(255) | Message ID |
| conversationId | VARCHAR(255) | Parent conversation |
| senderId | VARCHAR(255) | Message sender |
| senderName | VARCHAR(255) | Sender's name |
| text | LONGTEXT | Message content |
| timestamp | BIGINT | Message timestamp |
| is_read | BOOLEAN | Read status |
| created_at | TIMESTAMP | Record creation |

**Example Message:**
```json
{
  "id": "msg_456",
  "conversationId": "conv_123",
  "senderId": "user_789",
  "senderName": "Jane Smith",
  "text": "Yes, Friday works great! What time?",
  "timestamp": 1692518400,
  "is_read": true
}
```

---

### 7. **favorites** - Saved Books
Bookmarks for users to save interesting listings.

| Column | Type | Notes |
|--------|------|-------|
| id | VARCHAR(255) | Favorite ID |
| userId | VARCHAR(255) | User saving |
| bookId | VARCHAR(255) | Book being saved |
| timestamp | BIGINT | Save timestamp |
| created_at | TIMESTAMP | Record creation |

**Constraints:** Unique combination of userId and bookId (no duplicates)

---

### 8. **notifications** - User Notifications
Push and in-app notifications for users.

| Column | Type | Notes |
|--------|------|-------|
| id | VARCHAR(255) | Notification ID |
| userId | VARCHAR(255) | Recipient |
| title | VARCHAR(255) | Notification title |
| subtitle | TEXT | Notification body |
| timeAgo | VARCHAR(50) | Display time ("2 hours ago") |
| is_read | BOOLEAN | Read status |
| type | VARCHAR(50) | Type: 'notification' or 'claim' |
| conversationId | VARCHAR(255) | Related conversation (optional) |
| senderId | VARCHAR(255) | Notification sender (optional) |
| bookId | VARCHAR(255) | Related book (optional) |
| claimRequestId | VARCHAR(255) | Related claim (optional) |
| timestamp | BIGINT | Notification timestamp |
| created_at | TIMESTAMP | Record creation |

**Example Notification:**
```json
{
  "id": "notif_123",
  "userId": "user_456",
  "title": "New Claim Request",
  "subtitle": "Jane Smith requested your book 'The Lean Startup'",
  "type": "claim",
  "bookId": "book_123",
  "claimRequestId": "claim_789",
  "timestamp": 1692432000
}
```

---

## Data Relationships

```
users (1) ──┬─→ (N) books (as ownerId)
            ├─→ (N) claim_requests (as claimerId)
            ├─→ (N) claim_requests (as ownerId)
            ├─→ (N) chat_conversations (as participant)
            ├─→ (N) messages (as sender)
            ├─→ (N) favorites
            └─→ (N) notifications

books (1) ──┬─→ (N) claim_requests
            ├─→ (N) chat_conversations
            └─→ (N) favorites

claim_requests (1) ──→ (N) notifications

chat_conversations (1) ──→ (N) messages
```

---

## Common Queries

### Find all books by a user
```sql
SELECT * FROM books 
WHERE ownerId = 'user_456' 
ORDER BY postedTimestamp DESC;
```

### Find all claims for a book
```sql
SELECT * FROM claim_requests 
WHERE bookId = 'book_123' 
AND status IN ('PENDING', 'ACCEPTED');
```

### Get unread messages for a user
```sql
SELECT m.* FROM messages m
JOIN chat_conversations c ON m.conversationId = c.id
WHERE (c.participant1Id = 'user_789' OR c.participant2Id = 'user_789')
AND m.is_read = FALSE;
```

### Search books by title or author
```sql
SELECT * FROM books 
WHERE MATCH(title, author, description) AGAINST('+book' IN BOOLEAN MODE)
ORDER BY postedTimestamp DESC
LIMIT 20;
```

### Get user's favorite books
```sql
SELECT b.* FROM books b
JOIN favorites f ON b.id = f.bookId
WHERE f.userId = 'user_456'
ORDER BY f.timestamp DESC;
```

### Count unread notifications for user
```sql
SELECT COUNT(*) as unread_count 
FROM notifications
WHERE userId = 'user_456' AND is_read = FALSE;
```

---

## Setup Instructions

### 1. Import Schema
```bash
mysql -u bookora_user -p bookora_db < database_schema.sql
```

### 2. Configure API Connection
Update `config/db.php`:
```php
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'bookora_user');
define('DB_PASS', 'your_strong_password');
define('DB_NAME', 'bookora_db');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
```

### 3. Verify Installation
```sql
USE bookora_db;
SHOW TABLES;
DESC users;
```

---

## Best Practices

1. **Use prepared statements** to prevent SQL injection
2. **Index foreign keys** for faster JOINs
3. **Validate all input** from API requests
4. **Regular backups** of production database
5. **Monitor query performance** using EXPLAIN
6. **Use transactions** for multi-table operations
7. **Soft delete support** - Add `is_deleted` flag for audit trails
8. **Cache frequently accessed data** (user profiles, popular books)

---

**Schema Version:** 2.0 (Simplified)
**Last Updated:** 2026-08-14
**MySQL Compatibility:** 5.7+

