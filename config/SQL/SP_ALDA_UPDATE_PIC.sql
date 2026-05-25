USE [MOBILE_COLLECTION]
GO

/****** Object:  StoredProcedure [dbo].[SP_ALDA_UPDATE_PIC]    Script Date: 19/05/2026 ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO


CREATE PROCEDURE [dbo].[SP_ALDA_UPDATE_PIC]
(
    @usercreate    VARCHAR(200),
    @pic_nik_new   VARCHAR(200),
    @nomor_kontrak VARCHAR(50),
    @notes         VARCHAR(500) = NULL
)
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE
        @existing_id               BIGINT,
        @existing_status           VARCHAR(20),
        @existing_pic_nik          VARCHAR(50),
        @existing_pic_name         VARCHAR(200),
        @existing_pic_jabatan      VARCHAR(100),
        @existing_pic_pilar        VARCHAR(100),
        @existing_pic_lokasi_fisik VARCHAR(100),
        @existing_pic_lokasi_kerja VARCHAR(100),
        @existing_pic_area         VARCHAR(100),
        @existing_pic_cabang       VARCHAR(100),
        @existing_ver              INT,
        @existing_amount           DECIMAL(18, 2),
        @existing_contract_status  VARCHAR(50),
        @is_final                  BIT,
        @new_version               INT,
        @pic_name                  VARCHAR(200),
        @pic_jabatan               VARCHAR(100),
        @pic_pilar                 VARCHAR(100),
        @pic_lokasi_fisik          VARCHAR(100),
        @pic_lokasi_pekerjaan      VARCHAR(100),
        @pic_area                  VARCHAR(100),
        @pic_cabang                VARCHAR(100),
        @pic_branch_id             VARCHAR(10),
        @cust_area                 VARCHAR(50),
        @cust_branch_id            VARCHAR(20),
        @cust_branch_name          VARCHAR(200),
        @cust_portfolio            VARCHAR(20),
        @cust_name                 VARCHAR(300),
        @cust_address              VARCHAR(MAX),
        @cust_phone                VARCHAR(50),
        @cust_go_live              DATETIME,
        @cust_last_pay             DATETIME,
        @cust_merk                 VARCHAR(100),
        @cust_type                 VARCHAR(200),
        @cust_tahun                INT,
        @cust_contract_status      VARCHAR(50),
        @cust_amount               DECIMAL(18, 2),
        @submission_id             BIGINT,
        @DATE                      DATETIME        = GETDATE(),
        @time_str                  VARCHAR(20),
        @success                   BIT             = 0,
        @message                   VARCHAR(500);

    BEGIN TRY

        SELECT
              @existing_id               = PENUGASAN_ID,
              @existing_status           = STATUS,
              @existing_pic_nik          = PIC_NIK,
              @existing_pic_name         = PIC_NAME,
              @existing_pic_jabatan      = PIC_JABATAN,
              @existing_pic_pilar        = PIC_PILAR,
              @existing_pic_lokasi_fisik = PIC_LOKASI_FISIK,
              @existing_pic_lokasi_kerja = PIC_LOKASI_PEKERJAAN,
              @existing_pic_area         = PIC_AREA,
              @existing_pic_cabang       = PIC_CABANG,
              @existing_ver              = ASSIGN_VERSION,
              @existing_amount           = AMOUNT_TO_BE_PAID,
              @existing_contract_status  = CONTRACT_STATUS_SNAPSHOT
        FROM  ALDA_PENUGASAN WITH (NOLOCK)
        WHERE CONTRACT_NO = @nomor_kontrak;

        IF @existing_id IS NULL
        BEGIN
            SELECT
                CAST(0 AS BIT) AS success,
                'PENUGASAN TIDAK DITEMUKAN UNTUK NOMOR KONTRAK INI' AS message;
            RETURN;
        END

        SELECT @is_final = IS_FINAL
        FROM   ALDA_STATUS_REF WITH (NOLOCK)
        WHERE  STATUS_CODE = @existing_status;

        IF @is_final = 1
        BEGIN
            SELECT
                CAST(0 AS BIT) AS success,
                'PENUGASAN DENGAN STATUS ' + @existing_status + ' TIDAK DAPAT DIUBAH' AS message;
            RETURN;
        END

        IF NOT EXISTS (
            SELECT 1
            FROM   MASTER_ALDA_PIC WITH (NOLOCK)
            WHERE  REPLACE(NIK, '.', '') = REPLACE(@pic_nik_new, '.', '')
        )
        BEGIN
            SELECT
                CAST(0 AS BIT) AS success,
                'PIC BARU TIDAK DITEMUKAN' AS message;
            RETURN;
        END

        SELECT TOP 1
              @pic_name             = NAMA,
              @pic_jabatan          = JABATAN,
              @pic_pilar            = PILAR,
              @pic_lokasi_fisik     = LOKASI_FISIK,
              @pic_lokasi_pekerjaan = LOKASI_PEKERJAAN,
              @pic_area             = AREA,
              @pic_cabang           = CABANG,
              @pic_branch_id        = BRANCH_ID
        FROM  MASTER_ALDA_PIC WITH (NOLOCK)
        WHERE REPLACE(NIK, '.', '') = REPLACE(@pic_nik_new, '.', '');

        SELECT TOP 1
              @cust_area            = AREA,
              @cust_branch_id       = BRANCH_ID,
              @cust_branch_name     = BRANCH_NAME,
              @cust_portfolio       = PORTFOLIO,
              @cust_name            = CUSTOMER_NAME,
              @cust_address         = LEGAL_ADDRESS,
              @cust_phone           = CUSTOMER_PHONE,
              @cust_go_live         = GO_LIVE_DATE,
              @cust_last_pay        = TANGGAL_BAYAR_ANGS_TERAKHIR,
              @cust_merk            = MERK_KENDARAAN,
              @cust_type            = TYPE_KENDARAAN,
              @cust_tahun           = TAHUN_KENDARAAN,
              @cust_contract_status = CONTRACT_STATUS,
              @cust_amount          = AMOUNT_TO_BE_PAID
        FROM  MASTER_ALDA WITH (NOLOCK)
        WHERE NOMOR_KONTRAK = @nomor_kontrak;

        SET @new_version = ISNULL(@existing_ver, 0) + 1;

        SET @time_str      = REPLACE(REPLACE(REPLACE(REPLACE(CONVERT(VARCHAR(23), @DATE, 121), '-', ''), ' ', ''), ':', ''), '.', '');
        SET @submission_id = CAST(@time_str AS BIGINT);

        IF @notes IS NULL OR LTRIM(RTRIM(@notes)) = ''
            SET @notes = 'Perubahan PIC via Web';

        INSERT INTO ALDA_PENUGASAN_HISTORY
            ( PENUGASAN_ID, CONTRACT_NO, CHANGE_TYPE, CHANGE_REASON, CHANGED_AT, CHANGED_BY,
              STATUS_BEFORE, STATUS_AFTER,
              ASSIGN_VERSION_BEFORE, ASSIGN_VERSION_AFTER,
              PIC_NIK_BEFORE,  PIC_NAME_BEFORE,  PIC_JABATAN_BEFORE,  PIC_PILAR_BEFORE,
              PIC_LOKASI_FISIK_BEFORE, PIC_LOKASI_PEKERJAAN_BEFORE, PIC_AREA_BEFORE, PIC_CABANG_BEFORE,
              PIC_NIK_AFTER,   PIC_NAME_AFTER,   PIC_JABATAN_AFTER,   PIC_PILAR_AFTER,
              PIC_LOKASI_FISIK_AFTER,  PIC_LOKASI_PEKERJAAN_AFTER,  PIC_AREA_AFTER,  PIC_CABANG_AFTER,
              CONTRACT_STATUS_SNAPSHOT, AMOUNT_TO_BE_PAID )
        VALUES
            ( @existing_id, @nomor_kontrak, 'REASSIGN', @notes, @DATE, @usercreate,
              @existing_status, 'ASSIGNED',
              @existing_ver, @new_version,
              @existing_pic_nik,  @existing_pic_name,  @existing_pic_jabatan,  @existing_pic_pilar,
              @existing_pic_lokasi_fisik, @existing_pic_lokasi_kerja, @existing_pic_area, @existing_pic_cabang,
              @pic_nik_new,       @pic_name,            @pic_jabatan,           @pic_pilar,
              @pic_lokasi_fisik,  @pic_lokasi_pekerjaan, @pic_area,             @pic_cabang,
              @cust_contract_status, @cust_amount );

        -- Update penugasan with new PIC
        UPDATE ALDA_PENUGASAN
        SET    PIC_NIK                     = @pic_nik_new,
               PIC_NAME                    = @pic_name,
               PIC_JABATAN                 = @pic_jabatan,
               PIC_PILAR                   = @pic_pilar,
               PIC_LOKASI_FISIK            = @pic_lokasi_fisik,
               PIC_LOKASI_PEKERJAAN        = @pic_lokasi_pekerjaan,
               PIC_AREA                    = @pic_area,
               PIC_CABANG                  = @pic_cabang,
               PIC_BRANCH_ID               = @pic_branch_id,
               SUBMISSION_ID               = @submission_id,
               STATUS                      = 'ASSIGNED',
               ASSIGN_VERSION              = @new_version,
               NOTES                       = @notes,
               AREA                        = @cust_area,
               BRANCH_ID                   = @cust_branch_id,
               BRANCH_NAME                 = @cust_branch_name,
               PORTFOLIO                   = @cust_portfolio,
               CUSTOMER_NAME               = @cust_name,
               LEGAL_ADDRESS               = @cust_address,
               CUSTOMER_PHONE              = @cust_phone,
               GO_LIVE_DATE                = @cust_go_live,
               TANGGAL_BAYAR_ANGS_TERAKHIR = @cust_last_pay,
               MERK_KENDARAAN              = @cust_merk,
               TYPE_KENDARAAN              = @cust_type,
               TAHUN_KENDARAAN             = @cust_tahun,
               CONTRACT_STATUS_SNAPSHOT    = @cust_contract_status,
               AMOUNT_TO_BE_PAID           = @cust_amount,
               UPDATED_AT                  = @DATE,
               UPDATED_BY                  = @usercreate
        WHERE  CONTRACT_NO                 = @nomor_kontrak;

        SET @success = 1;
        SET @message = 'SUCCESS';

    END TRY
    BEGIN CATCH

        DECLARE
            @err_msg  NVARCHAR(4000),
            @err_line INT,
            @err_proc NVARCHAR(200);

        SELECT
              @err_msg  = ERROR_MESSAGE(),
              @err_line = ERROR_LINE(),
              @err_proc = ERROR_PROCEDURE();

        INSERT INTO dbo.ERROR_LOG (error_message, error_procedure, error_line, created_at)
        VALUES (@err_msg, @err_proc, @err_line, GETDATE());

        SET @message = 'FAILED: ' + ISNULL(@err_msg, 'Unknown error')
                     + ' (Line ' + CAST(@err_line AS VARCHAR(10)) + ')';
        SET @success = 0;

    END CATCH;

    SELECT @success AS success, @message AS message;

END;
GO