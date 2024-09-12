# Install
```
composer require --dev RikuKimuraTF/make-eloquent-model
```
*Since this will not be used in a production environment, be sure to add the dev option.

# Usage
After creating the tables, you can automatically generate a Domain model, Eloquent model, Domain repository, Eloquent repository, Factory, and Seeder.  
If a php file with the same name already exists, it will not be generated.
```
php artisan make:eloquent {table_name}
```
