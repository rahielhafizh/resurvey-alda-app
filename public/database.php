<?php
class Database
{
    private $conn;

    public function __construct()
    {
        $config_serverName = '172.16.1.76';
        $config_db = 'MOBILE_COLLECTION';
        $config_uid = 'sa';
        $config_pwd = 'user.200';

        $connectionInfo = [
            "Database" => $config_db,
            "UID" => $config_uid,
            "PWD" => $config_pwd,
        ];

        $this->conn = sqlsrv_connect($config_serverName, $connectionInfo);
        if ($this->conn === false) {
            die("Koneksi database gagal.");
        }
    }

    public function loginResurveyAlda($nik, $password)
    {
        $tsql = "{CALL SP_LOGIN_RESURVEY_ALDA(?, ?)}";
        $params = [[$nik, SQLSRV_PARAM_IN], [$password, SQLSRV_PARAM_IN]];
        $stmt = sqlsrv_query($this->conn, $tsql, $params);
        if ($stmt === false)
            return false;

        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
        return $row ? $row : null;
    }

    public function getPicByNik($nik)
    {
        $tsql = 'SELECT TOP 1 [NAMA], [IS_ACTIVE] FROM [dbo].[MASTER_ALDA_PIC] WHERE [NIK] = ?';
        $params = [[$nik, SQLSRV_PARAM_IN]];
        $stmt = sqlsrv_query($this->conn, $tsql, $params);
        if ($stmt === false)
            return false;

        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
        return $row ? $row : null;
    }

    public function getPicTasklistSummary($nik)
    {
        $tsql = '{CALL SP_ALDA_PIC_TASKLIST_SUMMARY(?)}';
        $params = [[$nik, SQLSRV_PARAM_IN]];
        $stmt = sqlsrv_query($this->conn, $tsql, $params);
        if ($stmt === false)
            return false;

        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
        return $row ? $row : null;
    }

    public function updateTaskStatus($penugasan_id, $nik, $status, $current_version)
    {
        $tsql = '{CALL SP_ALDA_PIC_UPDATE_STATUS(?, ?, ?, ?)}';
        $params = [
            [$penugasan_id, SQLSRV_PARAM_IN],
            [$nik, SQLSRV_PARAM_IN],
            [$status, SQLSRV_PARAM_IN],
            [$current_version, SQLSRV_PARAM_IN],
        ];
        $stmt = sqlsrv_query($this->conn, $tsql, $params);
        if ($stmt === false)
            return false;

        $result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
        return $result ? $result : null;
    }

    public function submitResurveyResult($penugasan_id, $nik, $current_version, $data)
    {
        $tsql = '{CALL SP_ALDA_SUBMIT_RESURVEY_RESULT(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)}';
        $params = [
            [$penugasan_id, SQLSRV_PARAM_IN],
            [$nik, SQLSRV_PARAM_IN],
            [$current_version, SQLSRV_PARAM_IN],
            [$data['lokasi_kunjungan'], SQLSRV_PARAM_IN],
            [$data['revisi_alamat_kunjungan'], SQLSRV_PARAM_IN],
            [$data['bertemu_dengan'], SQLSRV_PARAM_IN],
            [$data['pihak_non_debitur'], SQLSRV_PARAM_IN],
            [$data['keberadaan_unit'], SQLSRV_PARAM_IN],
            [$data['pernah_dikunjungi'], SQLSRV_PARAM_IN],
            [$data['penemuan_fraud'], SQLSRV_PARAM_IN],
            [$data['informasi_fraud'], SQLSRV_PARAM_IN],
            [$data['hasil_kunjungan'], SQLSRV_PARAM_IN],
            [$data['foto_path'], SQLSRV_PARAM_IN],
        ];

        $stmt = sqlsrv_query($this->conn, $tsql, $params);

        // Scope 2: Error detail extraction untuk memicu rollback physical file secara deterministik.
        if ($stmt === false) {
            $errors = sqlsrv_errors();
            $error_message = (is_array($errors) && isset($errors[0]['message'])) ? $errors[0]['message'] : 'Database transaction failed.';
            return ['success' => false, 'message' => '[DB ERROR] ' . $error_message];
        }

        $result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
        return $result ? $result : ['success' => false, 'message' => 'Empty response dari Database.'];
    }

    public function getTasks($nik, $status)
    {
        $tsql = '{CALL SP_ALDA_PIC_GET_TASKS(?, ?)}';
        $params = [[$nik, SQLSRV_PARAM_IN], [$status, SQLSRV_PARAM_IN]];
        $stmt = sqlsrv_query($this->conn, $tsql, $params);
        if ($stmt === false)
            return false;

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