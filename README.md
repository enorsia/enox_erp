# EnoX ERP

A comprehensive Enterprise Resource Planning (ERP) system built with Laravel, designed to streamline business operations and management processes.

## Installation Guide

Follow these steps to set up EnoX ERP on your local machine:

1. **Clone the repository**
```bash
   git clone https://github.com/enorsia/enox_erp.git
   cd enox_erp
```

2. **Configure environment**
```bash
   cp .env.example .env
```

3. **Install PHP dependencies**
```bash
   composer update
```

4. **Install Node dependencies**
```bash
   npm install
```

5. **Build frontend assets**
```bash
   npm run dev
```

6. **Generate application key**
```bash
   php artisan key:generate
```

6. **Datbase Migration**
```bash
   php artisan migrate
```

6. **Database Seed**
```bash
   php artisan db:seed
```

6. **Permissions sync**
```bash
   php artisan permissions:sync
```

7. **Start the development server**
```bash
   php artisan serve
```

8. **Access the application**
   
   Visit: [http://127.0.0.1:8000](http://127.0.0.1:8000)

## Requirements

- PHP >= 8.2
- Composer
- Node.js & NPM
- MySQL

## License

Proprietary - All Rights Reserved

Copyright © 2025 Enorsia. This software and associated documentation files are the proprietary property of Enorsia. Unauthorized copying, distribution, modification, or use of this software is strictly prohibited.
