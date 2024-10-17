
# Prasso Mmessaging
messaging functionality for Laravel 

To create the directory structure for your `Prasso_messaging` package after cloning the repository, you can use the following terminal commands:

```bash
# Navigate to the cloned repository directory
cd Prasso_messaging

# Create the 'src' directory and its subdirectories
mkdir -p src/Facades src/Http src/Models src/Commands src/config src/routes src/views

# Create the 'tests' directory
mkdir tests

# Create the 'composer.json' file
touch composer.json

# Create the 'MessagingServiceProvider.php' file inside the 'src' directory
touch src/MessagingServiceProvider.php
```

### Explanation of Commands:
- `cd Prasso_messaging`: Navigates to your package directory.
- `mkdir -p src/Facades src/Http src/Models src/Commands src/database src/config src/routes src/views`: Creates the `src` directory and its subdirectories in a single command.
- `mkdir tests`: Creates the `tests` directory.
- `touch composer.json`: Creates an empty `composer.json` file.
- `touch src/MessagingServiceProvider.php`: Creates an empty `MessagingServiceProvider.php` file inside the `src` directory.


second try
Certainly! Here are the terminal commands to create the directory structure you specified for the `Prasso_messaging` package. These commands assume that you have already cloned the repository and are working within the `Prasso_messaging` directory.

```bash
# Navigate to the package directory
cd Prasso_messaging

# Create the src directory and its subdirectories
mkdir -p src/Facades src/Http src/Models src/Commands src/config src/routes src/views

# Create the tests directory
mkdir tests

# Create the composer.json file
touch composer.json

# Create the ServiceProvider file
touch src/MessagingServiceProvider.php
```

After running these commands, your directory structure will be set up as follows:

```
Prasso_messaging/
├── src/
│   ├── YourPackageServiceProvider.php
│   ├── Facades/
│   ├── Http/
│   ├── Models/
│   ├── Commands/
│   ├── config/
│   ├── routes/
│   └── views/
├── tests/
├── composer.json
├── README.md
└── .gitignore
```

You can then proceed to edit the `composer.json` file and other files as needed for your package development.

