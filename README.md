# PaisaPilot

PaisaPilot is a modern personal finance and paper trading web application designed to help users track their expenses, manage virtual funds, and practice trading with real-time stock market data. The app features a highly interactive dashboard with dynamic visualizations and a live NSE stock ticker, providing a cohesive fintech SaaS experience.

## Features
- **Dashboard & Real-Time Visualization**: View and analyze your financial data through interactive and responsive charts.
- **Paper Trading Simulator**: Practice stock trading using virtual funds in a risk-free environment.
- **Live Stock Market Ticker**: Stay updated with real-time NSE market data integration.
- **Virtual Wallet Top-Up**: Experience a secure fund top-up flow for your virtual wallet.
- **Expense Tracking**: Efficiently log and categorize your day-to-day spending.
- **Modern Fintech Aesthetics**: Enjoy a refined, glassmorphic UI that feels premium and dynamic.

## Technologies Used
- **Backend & Logic**: PHP
- **Frontend**: HTML5, Vanilla CSS, JavaScript
- **Database**: MySQL

## Setup Instructions
1. Clone this repository to your local machine.
2. Ensure you have a local server environment configured (e.g., XAMPP, WAMP, or LAMP).
3. Create a `.env` file in the root directory (alongside `index.php`) and add your database credentials in the following format:
   ```ini
   DB_HOST="localhost"
   DB_USER="root"
   DB_PASS="your_password_here"
   DB_NAME="paisa_pilot"
   ```
4. Important: Do not upload the `.env` file to a public repository. If deploying to hosting platforms like InfinityFree, you will need to manually upload the `.env` file or configure environment variables in their panel.
5. Create the database automatically by visiting `http://localhost/PaisaPilot/setup.php` (or your configured virtual host path) in your browser.
6. Open your web browser and navigate to `http://localhost/PaisaPilot` (or your configured virtual host).

## License
Open Source
