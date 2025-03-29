# ConfPro Event Manager

A complete web application for professional conference management, built with Symfony 7.2.

![Symfony](https://img.shields.io/badge/symfony-%23000000.svg?style=for-the-badge&logo=symfony&logoColor=white)
![PHP](https://img.shields.io/badge/php-%23777BB4.svg?style=for-the-badge&logo=php&logoColor=white)
![Bootstrap](https://img.shields.io/badge/bootstrap-%23563D7C.svg?style=for-the-badge&logo=bootstrap&logoColor=white)

## 📋 Project Overview

ConfPro Event Manager is a web application that handles the complete lifecycle of professional conferences, from initial proposals to post-event recordings distribution.

The application connects different actors in the process:
- **Presenters** who propose conferences
- **Moderators** who validate content
- **Participants** who register for events
- **Administrators** who manage the entire process

## 🚀 Key Features

- **Conference Management**: creation, validation, scheduling, and archiving
- **Validation Workflow**: structured process for content moderation
- **Session Management**: scheduling with room conflict verification
- **Participant Registration**: with QR code generation for access
- **Automated Notifications**: reminders and communications with participants
- **Media & Feedback**: publication of presentations and collection of reviews

## 🛠️ Technologies Used

- **Symfony 7.2**: modern and high-performance PHP framework
- **Doctrine ORM**: for data management
- **Symfony Workflow**: to manage conference state transitions
- **Bootstrap 5**: for a responsive user interface
- **Twig**: template engine
- **UUIDs**: for secure identifiers

## ✨ Architectural Highlights

- **Layered Architecture**: clear separation between controllers, services, and repositories
- **SOLID Principles**: for maintainable and evolvable code
- **DRY and KISS Approaches**: simplicity and code reuse
- **Design Patterns**: Repository, Service, Factory
- **Clean Code**: well-documented, maintainable codebase

## 📥 Installation

### Prerequisites
- PHP 8.2 or higher
- Composer
- MySQL 8.0 or higher
- Symfony CLI (optional but recommended)

### Installation Steps

1. **Clone the project**

```bash
git clone https://github.com/yourusername/confpro-event-manager.git
cd confpro-event-manager
```

2. **Install dependencies**

```bash
composer install
```

3. **Configure the database**

Create a `.env.local` file at the project root and configure your database connection:

```
DATABASE_URL="mysql://user:password@127.0.0.1:3306/confpro?serverVersion=8.0"
```

4. **Create the database and tables**

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

5. **Load test data (optional)**

```bash
php bin/console doctrine:fixtures:load
```

6. **Start the development server**

```bash
symfony server:start
```
or
```bash
php -S 127.0.0.1:8000 -t public
```

7. **Access the application**

Open your browser and navigate to `http://localhost:8000`

## 👥 Test Accounts

After loading the fixtures, you can log in using the following accounts:

- **Admin**: admin@example.com / password
- **Moderator**: moderator1@example.com / password
- **Presenter**: presenter1@example.com / password
- **Participant**: participant1@example.com / password

## 📝 License

This project is licensed under the MIT License.