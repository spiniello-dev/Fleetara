# Fleetara

Fleetara is a comprehensive fleet management system designed to streamline the operations of businesses managing a fleet of vehicles. It provides tools for tracking assets, monitoring vehicle usage, managing maintenance schedules, and generating insightful reports to optimize fleet performance.

## Features

- **Asset Management**: Keep track of all vehicles and equipment in your fleet.
- **Driver Vehicle Inspection Reports (DVIRs)**: Manage and monitor vehicle inspection reports.
- **Fuel Tracking**: Record and analyze fuel consumption data.
- **Maintenance Scheduling**: Plan and track vehicle maintenance to reduce downtime.
- **Reports**: Generate detailed reports on mileage, fuel usage, and other key metrics.
- **User Management**: Secure login and password reset functionality for users.

## Technologies Used

- **Backend**: PHP
- **Frontend**: HTML, CSS, JavaScript
- **Database**: MySQL
- **Email**: PHPMailer for SMTP email functionality
- **Environment Management**: `vlucas/phpdotenv` for secure configuration

## Installation

1. Clone the repository:
   ```bash
   git clone <repository-url>
   ```
2. Navigate to the project directory:
   ```bash
   cd Fleetara
   ```
3. Install dependencies using Composer:
   ```bash
   composer install
   ```
4. Set up the `.env` file:
   - Copy `.env.example` to `.env`.
   - Update the `.env` file with your database credentials, SMTP settings, and other configuration values.
5. Run the application on your local server or deploy it to a web server.

## Usage

- Access the application via your web browser.
- Log in with your credentials.
- Use the dashboard to manage assets, view reports, and perform other fleet management tasks.

## Contributing

Contributions are welcome! Please fork the repository and submit a pull request for any enhancements or bug fixes.

## License

Fleetara is licensed under the MIT License. See the LICENSE file for details.
