# PaisaPilot

**Empowering Your Finances & Trading Skills**

[![Live Demo](https://img.shields.io/badge/Live%20Demo-paisa--pilot.free.nf-blue?style=for-the-badge&logo=globe)](https://paisa-pilot.free.nf)

PaisaPilot is a dual-purpose fintech web application that combines **expense tracking** with a **risk-free paper trading simulator**. Learn to trade stocks with real market data without any financial risk, while managing your day-to-day expenses.

---

## 🎯 Features

### 💰 Expense Management
- **Log & Categorize Expenses**: Track your daily spending with ease
- **Expense Analytics**: View detailed expense breakdowns and trends
- **Real-time Dashboard**: Monitor your spending habits at a glance

### 📈 Paper Trading Simulator
- **Virtual Trading**: Buy and sell stocks with virtual funds ($10,000 starting balance)
- **Live Market Data**: Real-time NSE (National Stock Exchange) integration
- **Portfolio Management**: Track your holdings, gains, and losses
- **Zero Financial Risk**: Learn trading without risking real money

### 👤 User Management
- **Secure Authentication**: Registration and login with password hashing
- **User Profile**: Manage your account details
- **Virtual Wallet**: Top-up your virtual balance anytime
- **Session Management**: Secure session handling

### 📊 Interactive Dashboard
- **Visual Charts & Graphs**: Beautiful Glassmorphic UI for financial overview
- **Quick Stats**: Total expenses, portfolio value, cash balance
- **Responsive Design**: Works seamlessly on desktop and mobile

---

## 🛠️ Tech Stack

| Category | Technology |
|----------|-----------|
| **Frontend** | HTML5, CSS3 (Glassmorphic Design), Vanilla JavaScript |
| **Backend** | PHP 7.4+ |
| **Database** | MySQL |
| **Hosting** | InfinityFree (PHP + MySQL) |
| **API Integration** | NSE Stock Market Data |

---

## 📋 Project Structure

```
PaisaPilot/
├── index.php                 # Entry point (redirects to login)
├── login.php                 # User login page
├── signup.php                # User registration page
├── logout.php                # Logout functionality
├── dashboard.php             # Main dashboard
├── stocks.php                # Stock trading interface
├── portfolio.php             # View holdings & trades
├── expenses.php              # Expense management
├── wallet.php                # Virtual wallet management
├── profile.php               # User profile management
├── setup.php                 # Database setup (run once)
├── db.php                    # Database connection
├── auth_check.php            # Authentication middleware
├── header.php                # Header component
├── footer.php                # Footer component
├── style.css                 # Styling (Glassmorphic design)
├── paisa_pilot.sql           # Database schema
├── .env                      # Environment variables (create from .env.example)
└── .gitignore                # Git ignore rules
```

---

## 🚀 Getting Started

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web Server (Apache, Nginx, or built-in PHP server)
- Internet connection (for stock market data)

### Local Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/aryanv0806-ops/PaisaPilot.git
   cd PaisaPilot
   ```

2. **Configure Database**
   - Create a `.env` file in the root directory:
   ```
   DB_HOST=localhost
   DB_USER=root
   DB_PASS=
   DB_NAME=paisapilot
   ```

3. **Create MySQL Database**
   ```bash
   mysql -u root < paisa_pilot.sql
   ```

4. **Run Setup Script** (Optional)
   - Visit `http://localhost/PaisaPilot/setup.php` to initialize the database

5. **Start the Server**
   ```bash
   # Using PHP built-in server (from project root)
   php -S localhost:8000
   ```
   Or place the folder in your web server's document root (Apache/Nginx)

6. **Access the Application**
   ```
   http://localhost:8000
   ```

---

## 🌐 Live Access

### 🎉 Live Demo
Access the live application here: **[paisa-pilot.free.nf](https://paisa-pilot.free.nf)**

### Demo Credentials
To test the application:
- **Email**: `demo@paisapilot.com`
- **Password**: `demo123`

Or create your own account by signing up!

---

## 📖 How to Use

### 1. **Registration & Login**
   - Click on "Sign Up" to create a new account
   - Enter your username, email, and password
   - Log in with your credentials

### 2. **View Dashboard**
   - See your total balance, expenses, and portfolio value
   - Quick overview of recent transactions

### 3. **Add Expenses**
   - Navigate to "Expenses" section
   - Click "Add Expense"
   - Fill in amount, category, and description
   - Your expenses appear in real-time

### 4. **Trade Stocks**
   - Go to "Stocks" section
   - Browse available NSE stocks
   - Enter quantity and click "Buy"
   - Your virtual balance updates immediately

### 5. **View Portfolio**
   - Check your holdings in the "Portfolio" section
   - See individual stock performance
   - Track your gains and losses

### 6. **Manage Wallet**
   - Navigate to "Wallet"
   - Top-up your virtual balance as needed
   - View balance history

---

## 🔐 Security Features

- ✅ Password hashing using PHP's `password_hash()` function
- ✅ SQL injection prevention with prepared statements
- ✅ Session-based authentication
- ✅ CSRF protection through session validation
- ✅ Environment-based configuration (sensitive data in .env)

---

## 🎨 UI/UX Features

- **Glassmorphic Design**: Modern, frosted glass aesthetic
- **Responsive Layout**: Works on all devices (mobile, tablet, desktop)
- **Real-time Updates**: AJAX-based interactions without page reload
- **Interactive Charts**: Visual representation of financial data
- **Dark Mode Support**: Modern color scheme

---

## 📊 Database Schema

### Key Tables
- **users**: User accounts and authentication
- **expenses**: User expense records with categories
- **trades**: Stock trading transactions
- **stocks**: Available stocks for trading

---

## 🚀 Future Enhancements

- [ ] Cryptocurrency paper trading
- [ ] Advanced charting with technical indicators
- [ ] AI-based spending analysis
- [ ] Global stock markets (NYSE, NASDAQ)
- [ ] Mobile app (React Native/Flutter)
- [ ] PWA (Progressive Web App) conversion
- [ ] Multi-language support
- [ ] Email notifications

---

## 🤝 Contributing

Contributions are welcome! Feel free to:
1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

---

## 📝 License

This project is licensed under the MIT License - see the LICENSE file for details.

---

## 👨‍💻 Author

**Aryan Varia** - [@aryanv0806-ops](https://github.com/aryanv0806-ops)

---

## 📧 Support & Contact

For issues, questions, or suggestions, please open an issue on GitHub or contact the author.

---

## 🙏 Acknowledgments

- NSE (National Stock Exchange) for market data
- InfinityFree for hosting
- The open-source community

---

**Happy Trading! 📈**
