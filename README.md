# Video Editor Telegram Bot

A professional Telegram bot for managing video submissions, reviews, and payments for video editors.

## 🌟 Features

- User registration and profile management
- Video submission and review system
- Editor rating system with monthly leaderboards
- Balance management and withdrawal requests
- Admin panel for content moderation
- Real-time notifications via webhook

## 🔧 Technical Requirements

- PHP 8.0 or higher
- MySQL 5.7 or higher
- SSL certificate (required for webhook)
- Composer
- Ubuntu 20.04+ (recommended)

## 📦 Installation

1. Clone the repository:
```bash
git clone https://github.com/yourusername/video-editor-bot.git
cd video-editor-bot
```

2. Install dependencies:
```bash
composer install
```

3. Configure environment variables:
```bash
cp .env.example .env
```

4. Edit `.env` file with your credentials:
```
BOT_TOKEN=your_telegram_bot_token
WEBHOOK_URL=https://your-domain.com/webhook.php
DB_HOST=localhost
DB_USER=your_database_user
DB_PASS=your_database_password
DB_NAME=video_editor_bot
ADMIN_CHAT_ID=your_admin_chat_id
REVIEW_CHANNEL_ID=your_review_channel_id
```

5. Set up the database:
```bash
mysql -u your_username -p your_database < database/schema.sql
```

6. Set up the webhook:
```bash
php scripts/set_webhook.php
```

## 🚀 Directory Structure

```
telegram-bot/
├── config/
├── database/
├── public/
├── src/
│   ├── Bot/
│   ├── Config/
│   ├── Controllers/
│   ├── Database/
│   ├── Helpers/
│   ├── Models/
│   └── Services/
├── .env
└── composer.json
```

## 🔒 Security Recommendations

1. Always use prepared statements for database queries
2. Validate all input data
3. Use secure HTTPS connections
4. Implement rate limiting
5. Regular security audits
6. Keep dependencies updated

## 💰 Payment System

- Each approved video adds 100,000 UZS to editor's balance
- Minimum withdrawal amount: 300,000 UZS
- All transactions are logged for transparency

## 👥 User Roles

1. Video Editors
   - Submit videos
   - Check balance
   - Request withdrawals

2. Admins
   - Review videos
   - Manage users
   - Process payments

3. Bot Owner
   - Full system access
   - Statistics overview
   - System configuration

## 📊 Commands

```
/start - Start the bot
/register - Register as video editor
/profile - View your profile
/balance - Check your balance
/withdraw - Request withdrawal
/stats - View your statistics
/help - Show help message
```

Admin Commands:
```
/admin - Access admin panel
/approve - Approve video
/reject - Reject video
/users - List users
/payments - View payment history
```

## 🤝 Contributing

Please read [CONTRIBUTING.md](CONTRIBUTING.md) for details on our code of conduct and the process for submitting pull requests.

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

For support, please contact [your-email@domain.com](mailto:your-email@domain.com)