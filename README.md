# Water Billing and Meter Reading System

Laravel 12, React, TypeScript, Inertia.js, and MySQL.

## Setup

1. Install PHP and Composer dependencies:

   ```bash
   composer install
   ```

2. Install frontend dependencies:

   ```bash
   npm install
   ```

3. Copy the environment file and generate the application key:

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. Create a MySQL database and update the database settings in `.env`:

   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=your_database_name
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

5. Run the migrations and build the frontend:

   ```bash
   php artisan migrate
   npm run build
   ```

6. Start the application:

   ```bash
   php artisan serve
   npm run dev
   ```

## Billing rules

- Consumption is calculated as current reading minus previous reading.
- The current reading cannot be lower than the previous reading.
- A bill uses only a water rate effective on its billing date.
- A new bill stores the base water charge. The 5% late penalty is calculated when a payment is recorded after the due date.
- Payments are full-payment only. The amount must exactly match the amount due, including any late penalty.
- Each bill can have only one payment.
- Rates already used by bills cannot be edited or deleted. Create a new rate for a new price or effective period.
- A rate effective period cannot overlap another rate period.
- Consumers and meters with billing history cannot be deleted or reassigned in ways that break history.

## Tests

Run the automated tests with:

```bash
php artisan test
```

## Notes

Business management routes require authentication. Add a role or policy layer before using this application with multiple permission levels. The project has not been security-audited for production use.
