USE [MOBILE_COLLECTION]
GO

/****** Object:  StoredProcedure [dbo].[SP_ALDA_CANCEL_ASSIGN]    Script Date: 19/05/2026 14:04:33 ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO


ALTER PROCEDURE [dbo].[SP_ALDA_CANCEL_ASSIGN]
(
    @usercreate    VARCHAR(200),
    @nomor_kontrak VARCHAR(50),
    @cancel_reason VARCHAR(500) = NULL
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
        @existing_amount           DECIMAL(18,2),
        @existing_contract_status  VARCHAR(50),
        @is_final                  BIT,
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
                'BELUM ADA PENUGASAN UNTUK NOMOR KONTRAK' AS message;
            RETURN;
        END

        SELECT @is_final = IS_FINAL
        FROM   ALDA_STATUS_REF WITH (NOLOCK)
        WHERE  STATUS_CODE = @existing_status;

        IF @is_final = 1
        BEGIN
            SELECT
                CAST(0 AS BIT) AS success,
                'PENUGASAN DENGAN STATUS ' + @existing_status + ' TIDAK DAPAT DIBATALKAN' AS message;
            RETURN;
        END

        INSERT INTO ALDA_PENUGASAN_HISTORY
            ( PENUGASAN_ID, CONTRACT_NO, CHANGE_TYPE, CHANGE_REASON, CHANGED_AT, CHANGED_BY,
              STATUS_BEFORE, STATUS_AFTER,
              ASSIGN_VERSION_BEFORE, ASSIGN_VERSION_AFTER,
              PIC_NIK_BEFORE,  PIC_NAME_BEFORE,  PIC_JABATAN_BEFORE,  PIC_PILAR_BEFORE,
              PIC_LOKASI_FISIK_BEFORE, PIC_LOKASI_PEKERJAAN_BEFORE, PIC_AREA_BEFORE, PIC_CABANG_BEFORE,
              PIC_NIK_AFTER, PIC_NAME_AFTER,
              CONTRACT_STATUS_SNAPSHOT, AMOUNT_TO_BE_PAID )
        VALUES
            ( @existing_id, @nomor_kontrak, 'CANCEL', @cancel_reason, GETDATE(), @usercreate,
              @existing_status, 'CANCELLED',
              @existing_ver, @existing_ver,
              @existing_pic_nik,  @existing_pic_name,  @existing_pic_jabatan,  @existing_pic_pilar,
              @existing_pic_lokasi_fisik, @existing_pic_lokasi_kerja, @existing_pic_area, @existing_pic_cabang,
              NULL, NULL,
              @existing_contract_status, @existing_amount );

        UPDATE ALDA_PENUGASAN
        SET    STATUS     = 'CANCELLED',
               NOTES      = @cancel_reason,
               UPDATED_AT = GETDATE(),
               UPDATED_BY = @usercreate
        WHERE  CONTRACT_NO = @nomor_kontrak;

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


