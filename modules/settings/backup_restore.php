<?php

require_once "../../includes/auth_check.php";
require_once "../../includes/role_guard.php";

grocerEaseRequireAdmin();

require_once "../../config/database.php";

$basePath = "/grocery-shop";
$page_title = "Settings - Backup & Restore";


/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token'])
) {
    $_SESSION['csrf_token'] =
        bin2hex(
            random_bytes(32)
        );
}

$csrfToken =
    $_SESSION['csrf_token'];


/*
|--------------------------------------------------------------------------
| HELPER - VERIFY CSRF
|--------------------------------------------------------------------------
*/

function verifyBackupCsrf(): void
{
    $sessionToken =
        $_SESSION['csrf_token']
        ?? '';

    $postedToken =
        $_POST['csrf_token']
        ?? '';

    if (
        !is_string($sessionToken) ||
        !is_string($postedToken) ||
        $sessionToken === '' ||
        $postedToken === '' ||
        !hash_equals(
            $sessionToken,
            $postedToken
        )
    ) {
        http_response_code(403);

        exit(
            'Invalid request token.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| HELPER - GENERATE DATABASE BACKUP
|--------------------------------------------------------------------------
*/

function generateGrocerEaseBackup(
    mysqli $conn
): string {

    $databaseName =
        'grocery_shop';

    $sql =
        "-- GrocerEase Database Backup\n"
        . "-- Generated: "
        . date('Y-m-d H:i:s')
        . "\n"
        . "-- Database: "
        . $databaseName
        . "\n\n"
        . "SET FOREIGN_KEY_CHECKS=0;\n"
        . "SET NAMES utf8mb4;\n\n";


    /*
    |--------------------------------------------------------------------------
    | LOAD TABLES
    |--------------------------------------------------------------------------
    */

    $tablesResult =
        mysqli_query(
            $conn,
            "SHOW TABLES"
        );


    if (!$tablesResult) {

        throw new RuntimeException(
            'Unable to read database tables.'
        );
    }


    while (
        $tableRow =
            mysqli_fetch_row(
                $tablesResult
            )
    ) {

        $table =
            (string) $tableRow[0];


        /*
        |--------------------------------------------------------------------------
        | TABLE STRUCTURE
        |--------------------------------------------------------------------------
        */

        $createResult =
            mysqli_query(
                $conn,
                "SHOW CREATE TABLE `"
                . str_replace(
                    '`',
                    '``',
                    $table
                )
                . "`"
            );


        if (!$createResult) {

            throw new RuntimeException(
                'Unable to read table structure.'
            );
        }


        $createRow =
            mysqli_fetch_row(
                $createResult
            );


        if (
            !$createRow ||
            !isset($createRow[1])
        ) {

            throw new RuntimeException(
                'Invalid table structure information.'
            );
        }


        $sql .=
            "-- --------------------------------------------------------\n";

        $sql .=
            "-- Table: `"
            . $table
            . "`\n";

        $sql .=
            "-- --------------------------------------------------------\n\n";


        $sql .=
            "DROP TABLE IF EXISTS `"
            . str_replace(
                '`',
                '``',
                $table
            )
            . "`;\n";


        $sql .=
            $createRow[1]
            . ";\n\n";


        /*
        |--------------------------------------------------------------------------
        | TABLE DATA
        |--------------------------------------------------------------------------
        */

        $dataResult =
            mysqli_query(
                $conn,
                "SELECT * FROM `"
                . str_replace(
                    '`',
                    '``',
                    $table
                )
                . "`"
            );


        if (!$dataResult) {

            throw new RuntimeException(
                'Unable to read table data.'
            );
        }


        while (
            $dataRow =
                mysqli_fetch_row(
                    $dataResult
                )
        ) {

            $values = [];


            foreach (
                $dataRow
                as $value
            ) {

                if ($value === null) {

                    $values[] =
                        'NULL';

                } else {

                    $values[] =
                        "'"
                        . mysqli_real_escape_string(
                            $conn,
                            (string) $value
                        )
                        . "'";
                }
            }


            $sql .=
                "INSERT INTO `"
                . str_replace(
                    '`',
                    '``',
                    $table
                )
                . "` VALUES ("
                . implode(
                    ', ',
                    $values
                )
                . ");\n";
        }


        $sql .= "\n";
    }


    $sql .=
        "SET FOREIGN_KEY_CHECKS=1;\n";


    return $sql;
}


/*
|--------------------------------------------------------------------------
| MESSAGE
|--------------------------------------------------------------------------
*/

$message = '';
$messageType = '';


/*
|--------------------------------------------------------------------------
| POST ACTIONS
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD']
    === 'POST'
) {

    verifyBackupCsrf();

    $action =
        (string) (
            $_POST['action']
            ?? ''
        );


    /*
    |--------------------------------------------------------------------------
    | DOWNLOAD BACKUP
    |--------------------------------------------------------------------------
    */

    if ($action === 'backup') {

        try {

            $sql =
                generateGrocerEaseBackup(
                    $conn
                );


            $filename =
                'grocery_backup_'
                . date(
                    'Y-m-d_H-i-s'
                )
                . '.sql';


            header(
                'Content-Type: application/sql; charset=UTF-8'
            );


            header(
                'Content-Disposition: attachment; filename="'
                . $filename
                . '"'
            );


            header(
                'Content-Length: '
                . strlen($sql)
            );


            header(
                'X-Content-Type-Options: nosniff'
            );


            echo $sql;

            exit;

        } catch (Throwable $exception) {

            $message =
                'The database backup could not be generated.';

            $messageType =
                'error';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | RESTORE DATABASE
    |--------------------------------------------------------------------------
    */

    if ($action === 'restore') {

        $currentPassword =
            (string) (
                $_POST[
                    'current_password'
                ]
                ?? ''
            );


        /*
        |--------------------------------------------------------------------------
        | REQUIRE ADMIN PASSWORD
        |--------------------------------------------------------------------------
        */

        if ($currentPassword === '') {

            $message =
                'Enter your current administrator password before restoring.';

            $messageType =
                'error';

        } else {

            $userId =
                (int) $_SESSION[
                    'user_id'
                ];


            $stmt = mysqli_prepare(
                $conn,
                "
                SELECT password
                FROM users
                WHERE user_id = ?
                LIMIT 1
                "
            );


            if (!$stmt) {

                $message =
                    'Unable to verify administrator credentials.';

                $messageType =
                    'error';

            } else {

                mysqli_stmt_bind_param(
                    $stmt,
                    'i',
                    $userId
                );


                mysqli_stmt_execute(
                    $stmt
                );


                $result =
                    mysqli_stmt_get_result(
                        $stmt
                    );


                $userRow =
                    mysqli_fetch_assoc(
                        $result
                    );


                mysqli_stmt_close(
                    $stmt
                );


                if (
                    !$userRow ||
                    !password_verify(
                        $currentPassword,
                        $userRow[
                            'password'
                        ]
                        ?? ''
                    )
                ) {

                    $message =
                        'Current administrator password is incorrect.';

                    $messageType =
                        'error';

                } else {


                    /*
                    |--------------------------------------------------------------------------
                    | VALIDATE UPLOAD
                    |--------------------------------------------------------------------------
                    */

                    if (
                        !isset(
                            $_FILES[
                                'backup_file'
                            ]
                        ) ||
                        !is_array(
                            $_FILES[
                                'backup_file'
                            ]
                        )
                    ) {

                        $message =
                            'Select a SQL backup file to restore.';

                        $messageType =
                            'error';

                    } else {

                        $upload =
                            $_FILES[
                                'backup_file'
                            ];


                        if (
                            $upload['error']
                            !== UPLOAD_ERR_OK
                        ) {

                            $message =
                                'The backup file could not be uploaded.';

                            $messageType =
                                'error';

                        } elseif (
                            (int) $upload[
                                'size'
                            ] <= 0
                        ) {

                            $message =
                                'The selected backup file is empty.';

                            $messageType =
                                'error';

                        } elseif (
                            (int) $upload[
                                'size'
                            ] >
                            10 * 1024 * 1024
                        ) {

                            $message =
                                'The backup file is larger than the 10 MB restore limit.';

                            $messageType =
                                'error';

                        } else {

                            $originalName =
                                (string) (
                                    $upload[
                                        'name'
                                    ]
                                    ?? ''
                                );


                            $extension =
                                strtolower(
                                    pathinfo(
                                        $originalName,
                                        PATHINFO_EXTENSION
                                    )
                                );


                            if (
                                $extension
                                !== 'sql'
                            ) {

                                $message =
                                    'Only .sql backup files can be restored.';

                                $messageType =
                                    'error';

                            } else {

                                $temporaryPath =
                                    (string) (
                                        $upload[
                                            'tmp_name'
                                        ]
                                        ?? ''
                                    );


                                $sql =
                                    file_get_contents(
                                        $temporaryPath
                                    );


                                if (
                                    $sql === false ||
                                    trim($sql)
                                    === ''
                                ) {

                                    $message =
                                        'The SQL backup file could not be read.';

                                    $messageType =
                                        'error';

                                } else {


                                    /*
                                    |--------------------------------------------------------------------------
                                    | REQUIRE GROCER EASE BACKUP MARKER
                                    |--------------------------------------------------------------------------
                                    */

                                    if (
                                        strpos(
                                            $sql,
                                            '-- GrocerEase Database Backup'
                                        )
                                        === false
                                    ) {

                                        $message =
                                            'This file is not recognized as a GrocerEase database backup.';

                                        $messageType =
                                            'error';

                                    } else {


                                        /*
                                        |--------------------------------------------------------------------------
                                        | BLOCK DATABASE / SERVER LEVEL COMMANDS
                                        |--------------------------------------------------------------------------
                                        */

                                        $forbiddenPattern =
                                            '/\b('
                                            . 'CREATE\s+DATABASE'
                                            . '|DROP\s+DATABASE'
                                            . '|ALTER\s+DATABASE'
                                            . '|USE\s+[`\w]'
                                            . '|GRANT\s+'
                                            . '|REVOKE\s+'
                                            . '|LOAD\s+DATA'
                                            . '|INTO\s+OUTFILE'
                                            . '|INTO\s+DUMPFILE'
                                            . '|SOURCE\s+'
                                            . '|DELIMITER\s+'
                                            . ')\b/i';


                                        if (
                                            preg_match(
                                                $forbiddenPattern,
                                                $sql
                                            )
                                        ) {

                                            $message =
                                                'The SQL file contains commands that are not allowed by GrocerEase restore.';

                                            $messageType =
                                                'error';

                                        } else {


                                            /*
                                            |--------------------------------------------------------------------------
                                            | CREATE PRE-RESTORE SAFETY COPY
                                            |--------------------------------------------------------------------------
                                            */

                                            try {

                                                $preRestoreBackup =
                                                    generateGrocerEaseBackup(
                                                        $conn
                                                    );


                                                $safetyFile =
                                                    sys_get_temp_dir()
                                                    . DIRECTORY_SEPARATOR
                                                    . 'grocerease_pre_restore_'
                                                    . date(
                                                        'Ymd_His'
                                                    )
                                                    . '.sql';


                                                @file_put_contents(
                                                    $safetyFile,
                                                    $preRestoreBackup
                                                );

                                            } catch (
                                                Throwable $exception
                                            ) {

                                                $message =
                                                    'A safety backup could not be created, so the restore was cancelled.';

                                                $messageType =
                                                    'error';

                                                $safetyFile =
                                                    '';
                                            }


                                            /*
                                            |--------------------------------------------------------------------------
                                            | EXECUTE RESTORE
                                            |--------------------------------------------------------------------------
                                            */

                                            if (
                                                $messageType
                                                !== 'error'
                                            ) {

                                                $restoreSuccess =
                                                    mysqli_multi_query(
                                                        $conn,
                                                        $sql
                                                    );


                                                $restoreError =
                                                    '';


                                                if (
                                                    !$restoreSuccess
                                                ) {

                                                    $restoreError =
                                                        mysqli_error(
                                                            $conn
                                                        );

                                                } else {


                                                    /*
                                                    |--------------------------------------------------------------------------
                                                    | DRAIN MULTI-QUERY RESULTS
                                                    |--------------------------------------------------------------------------
                                                    */

                                                    do {

                                                        $multiResult =
                                                            mysqli_store_result(
                                                                $conn
                                                            );


                                                        if (
                                                            $multiResult
                                                            instanceof mysqli_result
                                                        ) {

                                                            mysqli_free_result(
                                                                $multiResult
                                                            );
                                                        }


                                                        if (
                                                            !mysqli_more_results(
                                                                $conn
                                                            )
                                                        ) {
                                                            break;
                                                        }


                                                        if (
                                                            !mysqli_next_result(
                                                                $conn
                                                            )
                                                        ) {

                                                            $restoreError =
                                                                mysqli_error(
                                                                    $conn
                                                                );

                                                            break;
                                                        }

                                                    } while (true);
                                                }


                                                /*
                                                |--------------------------------------------------------------------------
                                                | RESTORE RESULT
                                                |--------------------------------------------------------------------------
                                                */

                                                if (
                                                    $restoreError
                                                    !== ''
                                                ) {

                                                    $message =
                                                        'The restore did not complete successfully. '
                                                        . 'A pre-restore safety backup was created in the system temporary directory.';

                                                    $messageType =
                                                        'error';

                                                } else {

                                                    /*
                                                    |--------------------------------------------------------------------------
                                                    | SESSION MAY NOW CONTAIN OLD USER DATA
                                                    |--------------------------------------------------------------------------
                                                    */

                                                    session_regenerate_id(
                                                        true
                                                    );


                                                    $message =
                                                        'Database restore completed successfully.';

                                                    $messageType =
                                                        'success';
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| DATABASE SUMMARY
|--------------------------------------------------------------------------
*/

$tableNames = [
    'categories',
    'customers',
    'payments',
    'products',
    'purchases',
    'purchase_items',
    'sales',
    'sale_items',
    'settings',
    'suppliers',
    'users'
];


$databaseStats = [];


foreach (
    $tableNames
    as $tableName
) {

    $safeTableName =
        str_replace(
            '`',
            '``',
            $tableName
        );


    $result =
        mysqli_query(
            $conn,
            "
            SELECT COUNT(*) AS total
            FROM `{$safeTableName}`
            "
        );


    if ($result) {

        $row =
            mysqli_fetch_assoc(
                $result
            );


        $databaseStats[
            $tableName
        ] =
            (int) (
                $row['total']
                ?? 0
            );

    } else {

        $databaseStats[
            $tableName
        ] = 0;
    }
}


/*
|--------------------------------------------------------------------------
| SHARED HEADER
|--------------------------------------------------------------------------
*/

require_once "../../includes/header.php";

?>


<link
    rel="stylesheet"
    href="../../assets/css/sidebar.css"
>

<link
    rel="stylesheet"
    href="../../assets/css/topbar.css"
>

<link
    rel="stylesheet"
    href="../../assets/css/dashboard-layout.css"
>

<link
    rel="stylesheet"
    href="../../assets/css/module.css"
>


<style>

.backup-grid {
    display: grid;
    grid-template-columns:
        minmax(0, 1.2fr)
        minmax(320px, 0.8fr);
    gap: 20px;
}


.backup-note {
    padding: 15px;
    margin: 16px 0;
    border-radius: 8px;
    background: #eff6ff;
    color: #475569;
    font-size: 14px;
}


.restore-warning {
    padding: 15px;
    margin: 16px 0;
    border-radius: 8px;
    background: #fff7ed;
    color: #9a3412;
    font-size: 14px;
}


.restore-warning strong {
    color: #7c2d12;
}


.database-summary {
    margin-top: 20px;
}


.backup-file-input {
    display: block;
    width: 100%;
    padding: 10px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #ffffff;
}


@media (max-width: 900px) {

    .backup-grid {
        grid-template-columns: 1fr;
    }
}

</style>


<div class="app-layout">


    <aside class="app-sidebar-slot">

        <?php
        require_once "../../includes/sidebar.php";
        ?>

    </aside>


    <div class="app-main-slot">


        <header class="app-topbar-slot">

            <?php
            require_once "../../includes/topbar.php";
            ?>

        </header>


        <main class="dashboard-main-content">


            <div
                class="dashboard-page"
                style="padding: 24px;"
            >


                <div class="page-header">

                    <div>

                        <h1>
                            Backup &amp; Restore
                        </h1>

                        <p>
                            Download a complete SQL backup or restore
                            GrocerEase from a previously generated backup.
                        </p>

                    </div>

                </div>


                <?php if ($message !== ''): ?>

                    <div
                        class="alert <?php
                            echo
                                $messageType
                                === 'success'
                                    ? 'alert-success'
                                    : 'alert-danger';
                        ?>"
                    >

                        <?php
                        echo htmlspecialchars(
                            $message,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>

                    </div>

                <?php endif; ?>


                <div class="backup-grid">


                    <!-- =================================================
                         BACKUP
                    ================================================= -->

                    <div class="card">


                        <div class="card-title">
                            Database Backup
                        </div>


                        <p>
                            Download the current GrocerEase database
                            as an SQL file.
                        </p>


                        <div class="backup-note">

                            <strong>
                                Backup includes:
                            </strong>

                            <br><br>

                            Products and categories<br>
                            Suppliers and purchases<br>
                            Customers and payments<br>
                            Sales and sale items<br>
                            User accounts and settings

                        </div>


                        <form method="POST">


                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?php
                                    echo htmlspecialchars(
                                        $csrfToken,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="action"
                                value="backup"
                            >


                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                Download SQL Backup
                            </button>


                        </form>


                    </div>


                    <!-- =================================================
                         RESTORE
                    ================================================= -->

                    <div class="card">


                        <div class="card-title">
                            Restore Database
                        </div>


                        <p>
                            Restore the database using a SQL backup
                            previously generated by GrocerEase.
                        </p>


                        <div class="restore-warning">

                            <strong>
                                Warning:
                            </strong>

                            Restoring replaces the current database
                            tables and records with the contents of
                            the selected backup file.

                        </div>


                        <form
                            method="POST"
                            enctype="multipart/form-data"
                            onsubmit="
                                return confirm(
                                    'Restore this database backup? Current database records may be replaced.'
                                );
                            "
                        >


                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?php
                                    echo htmlspecialchars(
                                        $csrfToken,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="action"
                                value="restore"
                            >


                            <div
                                class="form-group"
                                style="margin-bottom: 14px;"
                            >

                                <label>
                                    SQL Backup File
                                </label>


                                <input
                                    type="file"
                                    name="backup_file"
                                    class="backup-file-input"
                                    accept=".sql"
                                    required
                                >

                            </div>


                            <div
                                class="form-group"
                                style="margin-bottom: 18px;"
                            >

                                <label>
                                    Current Administrator Password
                                </label>


                                <input
                                    type="password"
                                    name="current_password"
                                    autocomplete="current-password"
                                    required
                                    placeholder="Confirm your password"
                                >

                            </div>


                            <button
                                type="submit"
                                class="btn btn-danger"
                            >
                                Restore Database
                            </button>


                        </form>


                    </div>


                </div>


                <!-- =================================================
                     DATABASE SUMMARY
                ================================================= -->

                <div class="card database-summary">


                    <div class="card-title">
                        Database Summary
                    </div>


                    <div class="table-wrapper">


                        <table class="data-table">


                            <thead>

                                <tr>

                                    <th>
                                        Table
                                    </th>

                                    <th>
                                        Records
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php
                                foreach (
                                    $databaseStats
                                    as $tableName =>
                                        $recordCount
                                ):
                                ?>


                                    <tr>

                                        <td>

                                            <?php
                                            echo htmlspecialchars(
                                                ucwords(
                                                    str_replace(
                                                        '_',
                                                        ' ',
                                                        $tableName
                                                    )
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                            ?>

                                        </td>


                                        <td>

                                            <span
                                                class="badge badge-info"
                                            >

                                                <?php
                                                echo
                                                    $recordCount;
                                                ?>

                                            </span>

                                        </td>

                                    </tr>


                                <?php endforeach; ?>


                            </tbody>


                        </table>


                    </div>


                </div>


            </div>


        </main>


    </div>


</div>


<?php

require_once "../../includes/footer.php";

?>