# Fleetara

Fleetara is a comprehensive fleet management platform designed to give businesses a **one-pane-of-glass view** into their fleet operations. By combining data from the **Samsara** and **Fleetio** APIs, Fleetara unifies **Driver Vehicle Inspection Reports (DVIRs)**, **fuel metrics**, **trailers**, **rental information**, and other fleet tracking tools into a single reporting and management interface.

This integration reduces the need to jump between multiple systems, making it easier to monitor vehicle health, track costs, and optimize performance across your entire fleet.

## Features

- **Unified API Integration**: Consolidates data from Samsara and Fleetio for seamless fleet visibility.
- **Asset Management**: Keep track of all vehicles, trailers, and rental equipment.
- **Driver Vehicle Inspection Reports (DVIRs)**: Centralize inspection reports across systems for compliance and safety.
- **Fuel Tracking**: Collect, record, and analyze consumption metrics to identify inefficiencies.
- **Maintenance Scheduling**: Plan and monitor preventive maintenance to minimize downtime.
- **Reporting & Analytics**: Generate detailed reports on mileage, fuel usage, DVIR compliance, and more.
- **User Management**: Secure login, role-based access, and password reset functionality.

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