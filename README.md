# Smart Document Processing System

## Live Demo
https://projekat.kupidres.com

Note: The domain is reused from a previous project and is used here only to host this system for demonstration purposes.

---

## Overview

This is a demo version of a smart document processing system designed to process real-world business documents such as invoices and purchase orders.

The system allows users to upload documents, automatically extract structured data, validate it, and review it through a clean workflow with admin approval.

---

## How It Works (Architecture)

Upload → Parser → Validation → Review → Admin Decision

### 1. Upload Layer
- Upload documents (PDF, CSV, TXT, Images)
- Store files on server
- Save metadata to database

### 2. Parsing Layer
- Extract raw text depending on file type
- Normalize:
  - numbers
  - dates
  - currency
- Detect document type (invoice / purchase order)

### 3. Validation Engine
- Runs automatic checks
- Detects inconsistencies
- Generates issues (stored as JSON)
- Assigns document status

### 4. Review Layer
- Displays extracted data
- Highlights issues
- Allows manual corrections
- Auto recalculates totals

### 5. Admin Layer
- Admin reviews validated documents
- Can:
  - Accept
  - Reject
  - Send back for correction

---

## Features

- Multi-format upload (PDF, CSV, TXT, Images)
- Automatic data extraction
- Validation engine (totals, fields, dates)
- Manual review interface
- Admin approval system
- Real-time recalculation of totals

---

## Validation Logic

### Required Fields
- Supplier name
- Document number
- Issue date
- Currency

### Financial Validation

subtotal + tax = total

If mismatch:
- Document flagged
- Status → Needs Review

### Date Handling
- Issue date required
- Due date auto-generated if missing (+30 days)

### Data Normalization
- Converts different number formats
- Standardizes date formats

### Issue Tracking
- Stored as structured JSON
- Displayed in UI

---

## Tech Stack

- PHP
- MySQL
- JavaScript
- HTML / CSS

---

## Setup

After cloning the repository, install dependencies:

composer install

The `vendor/` folder is required for proper functionality.

---

## AI Usage

This project was developed with assistance from:
- ChatGPT
- Claude.ai

Used for:
- System design guidance
- Code structuring
- Debugging and optimization
- UI/UX improvements

---

## Future Improvements

- OCR (text extraction from images)
- AI-based parsing (LLM integration)
- User authentication (login & registration)
- Client management system (admin dashboard with users)
- Advanced analytics dashboard
- API authentication (token-based)
- Background processing (queue system)

---

## Notes

- This is a demo system
- Parsing is simplified
- OCR and AI parsing for future improvements

---

## Author

Besim
