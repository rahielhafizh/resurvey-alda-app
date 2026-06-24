<?php
declare(strict_types=1);

class Database
{
    private $conn;

    public function __construct()
    {
        $config_serverName = '172.16.1.76';
        $config_db = 'MOBILE_COLLECTION';
        $config_uid = 'sa';
        $config_pwd = 'user.200';

        $connectionInfo = array(
            "Database" => $config_db,
            "UID" => $config_uid,
            "PWD" => $config_pwd
        );

        $this->conn = sqlsrv_connect($config_serverName, $connectionInfo);

        if ($this->conn === false) {
            die("Koneksi database gagal. Periksa konfigurasi pada file database.php.");
        }
    }

    public function getConnection()
    {
        return $this->conn;
    }

    public function loginResurveyAlda($nik, $password)
    {
        $tsql = "{CALL SP_LOGIN_RESURVEY_ALDA(?, ?)}";
        $params = array(
            array($nik, SQLSRV_PARAM_IN),
            array($password, SQLSRV_PARAM_IN)
        );

        $stmt = sqlsrv_query($this->conn, $tsql, $params);

        if ($stmt === false) {
            return false;
        }

        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);

        return $row ? $row : null;
    }

    public function getPicByNik($nik)
    {
        $tsql = 'SELECT [NAMA], [IS_ACTIVE] FROM [dbo].[MASTER_ALDA_PIC] WHERE [NIK] = ?';
        $params = array(
            array($nik, SQLSRV_PARAM_IN)
        );

        $stmt = sqlsrv_query($this->conn, $tsql, $params);

        if ($stmt === false) {
            return false;
        }

        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);

        return $row ? $row : null;
    }

    public function getPicTasklistSummary($nik)
    {
        $tsql = '{CALL SP_ALDA_PIC_TASKLIST_SUMMARY(?)}';
        $params = array(
            array($nik, SQLSRV_PARAM_IN)
        );

        $stmt = sqlsrv_query($this->conn, $tsql, $params);

        if ($stmt === false) {
            return false;
        }

        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);

        return $row ? $row : null;
    }

    public function updateTaskStatus($penugasan_id, $nik, $status)
    {
        $tsql = '{CALL SP_ALDA_PIC_UPDATE_STATUS(?, ?, ?)}';
        $params = [
            [$penugasan_id, SQLSRV_PARAM_IN],
            [$nik, SQLSRV_PARAM_IN],
            [$status, SQLSRV_PARAM_IN],
        ];

        $stmt = sqlsrv_query($this->conn, $tsql, $params);

        if ($stmt === false) {
            return false;
        }

        $result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);

        return $result ? $result : null;
    }

    public function getTasks($nik, $status)
    {
        $tsql = '{CALL SP_ALDA_PIC_GET_TASKS(?, ?)}';
        $params = [
            [$nik, SQLSRV_PARAM_IN],
            [$status, SQLSRV_PARAM_IN],
        ];

        $stmt = sqlsrv_query($this->conn, $tsql, $params);

        if ($stmt === false) {
            return false;
        }

        $tasks = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $tasks[] = $row;
        }
        sqlsrv_free_stmt($stmt);

        return $tasks;
    }

    public function close()
    {
        if ($this->conn) {
            sqlsrv_close($this->conn);
        }
    }
}
?>