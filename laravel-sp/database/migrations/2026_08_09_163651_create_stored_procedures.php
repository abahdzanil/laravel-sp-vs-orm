<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function createProcedure(string $name, string $body): void
    {
        DB::unprepared("DROP PROCEDURE IF EXISTS {$name}");
        DB::unprepared($body);
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->createProcedure('sp_user_create', "
            CREATE PROCEDURE sp_user_create(
                IN p_name VARCHAR(150), IN p_email VARCHAR(150), IN p_password VARCHAR(255)
            )
            BEGIN
                INSERT INTO users (name, email, password, created_at, updated_at)
                VALUES (p_name, p_email, p_password, UTC_TIMESTAMP(), UTC_TIMESTAMP());
            END
        ");

        $this->createProcedure('sp_user_find_by_email', "
            CREATE PROCEDURE sp_user_find_by_email(IN p_email VARCHAR(150))
            BEGIN
                SELECT * FROM users WHERE email = p_email LIMIT 1;
            END
        ");

        // ===== PRODUCTS =====
        $this->createProcedure('sp_product_list', "
            CREATE PROCEDURE sp_product_list(IN p_limit INT, IN p_offset INT)
            BEGIN
                SELECT * FROM products ORDER BY id DESC LIMIT p_limit OFFSET p_offset;
            END
        ");

        $this->createProcedure('sp_product_count', "
            CREATE PROCEDURE sp_product_count()
            BEGIN
                SELECT COUNT(*) AS total FROM products;
            END
        ");

        $this->createProcedure('sp_product_find', "
            CREATE PROCEDURE sp_product_find(IN p_id BIGINT)
            BEGIN
                SELECT * FROM products WHERE id = p_id;
            END
        ");

        $this->createProcedure('sp_product_create', "
            CREATE PROCEDURE sp_product_create(
                IN p_name VARCHAR(150), IN p_sku VARCHAR(50), IN p_category VARCHAR(100),
                IN p_price DECIMAL(12,2), IN p_stock INT
            )
            BEGIN
                INSERT INTO products (name, sku, category, price, stock, created_at, updated_at)
                VALUES (p_name, p_sku, p_category, p_price, p_stock, UTC_TIMESTAMP(), UTC_TIMESTAMP());
            END
        ");

        $this->createProcedure('sp_product_update', "
            CREATE PROCEDURE sp_product_update(
                IN p_id BIGINT, IN p_name VARCHAR(150), IN p_sku VARCHAR(50),
                IN p_category VARCHAR(100), IN p_price DECIMAL(12,2), IN p_stock INT
            )
            BEGIN
                UPDATE products
                SET name = p_name, sku = p_sku, category = p_category,
                    price = p_price, stock = p_stock, updated_at = UTC_TIMESTAMP()
                WHERE id = p_id;
            END
        ");

        $this->createProcedure('sp_product_delete', "
            CREATE PROCEDURE sp_product_delete(IN p_id BIGINT)
            BEGIN
                DELETE FROM products WHERE id = p_id;
            END
        ");

        $this->createProcedure('sp_product_sku_exists', "
            CREATE PROCEDURE sp_product_sku_exists(IN p_sku VARCHAR(50), IN p_exclude_id BIGINT)
            BEGIN
                SELECT COUNT(*) AS total FROM products WHERE sku = p_sku AND id != p_exclude_id;
            END
        ");

        // ===== EMPLOYEES =====
        $this->createProcedure('sp_employee_list', "
            CREATE PROCEDURE sp_employee_list(IN p_limit INT, IN p_offset INT)
            BEGIN
                SELECT * FROM employees ORDER BY id DESC LIMIT p_limit OFFSET p_offset;
            END
        ");

        $this->createProcedure('sp_employee_count', "
            CREATE PROCEDURE sp_employee_count()
            BEGIN
                SELECT COUNT(*) AS total FROM employees;
            END
        ");

        $this->createProcedure('sp_employee_find', "
            CREATE PROCEDURE sp_employee_find(IN p_id BIGINT)
            BEGIN
                SELECT * FROM employees WHERE id = p_id;
            END
        ");

        $this->createProcedure('sp_employee_create', "
            CREATE PROCEDURE sp_employee_create(
                IN p_name VARCHAR(150), IN p_email VARCHAR(150), IN p_position VARCHAR(100),
                IN p_department VARCHAR(100), IN p_salary DECIMAL(12,2), IN p_hire_date DATE
            )
            BEGIN
                INSERT INTO employees (name, email, position, department, salary, hire_date, created_at, updated_at)
                VALUES (p_name, p_email, p_position, p_department, p_salary, p_hire_date, UTC_TIMESTAMP(), UTC_TIMESTAMP());
            END
        ");

        $this->createProcedure('sp_employee_update', "
            CREATE PROCEDURE sp_employee_update(
                IN p_id BIGINT, IN p_name VARCHAR(150), IN p_email VARCHAR(150),
                IN p_position VARCHAR(100), IN p_department VARCHAR(100),
                IN p_salary DECIMAL(12,2), IN p_hire_date DATE
            )
            BEGIN
                UPDATE employees
                SET name = p_name, email = p_email, position = p_position,
                    department = p_department, salary = p_salary, hire_date = p_hire_date,
                    updated_at = UTC_TIMESTAMP()
                WHERE id = p_id;
            END
        ");

        $this->createProcedure('sp_employee_delete', "
            CREATE PROCEDURE sp_employee_delete(IN p_id BIGINT)
            BEGIN
                DELETE FROM employees WHERE id = p_id;
            END
        ");

        $this->createProcedure('sp_employee_email_exists', "
            CREATE PROCEDURE sp_employee_email_exists(IN p_email VARCHAR(150), IN p_exclude_id BIGINT)
            BEGIN
                SELECT COUNT(*) AS total FROM employees WHERE email = p_email AND id != p_exclude_id;
            END
        ");
    }

    public function down(): void
    {
        $procedures = [
            'sp_user_create', 'sp_user_find_by_email',
            'sp_product_list', 'sp_product_count', 'sp_product_find',
            'sp_product_create', 'sp_product_update', 'sp_product_delete', 'sp_product_sku_exists',
            'sp_employee_list', 'sp_employee_count', 'sp_employee_find',
            'sp_employee_create', 'sp_employee_update', 'sp_employee_delete', 'sp_employee_email_exists',
        ];

        foreach ($procedures as $proc) {
            DB::unprepared("DROP PROCEDURE IF EXISTS {$proc}");
        }
    }
};
