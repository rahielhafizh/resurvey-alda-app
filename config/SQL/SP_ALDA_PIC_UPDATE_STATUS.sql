USE [MOBILE_COLLECTION]
GO

SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO

ALTER PROCEDURE [dbo].[SP_ALDA_PIC_UPDATE_STATUS]
(
    @PENUGASAN_ID BIGINT,
    @PIC_NIK      VARCHAR(50),
    @NEW_STATUS   VARCHAR(20)
)
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE
        @contract_no           VARCHAR(50),
        @existing_status       VARCHAR(20),
        @existing_ver          INT,
        @new_version           INT,
        @success               BIT = 0,
        @message               VARCHAR(500);

    BEGIN TRY
        -- Validasi penugasan
        SELECT 
            @contract_no = CONTRACT_NO,
            @existing_status = STATUS,
            @existing_ver = ASSIGN_VERSION
        FROM ALDA_PENUGASAN WITH (NOLOCK)
        WHERE PENUGASAN_ID = @PENUGASAN_ID 
          AND REPLACE(PIC_NIK, '.', '') = REPLACE(@PIC_NIK, '.', '');

        IF @contract_no IS NULL
        BEGIN
            SELECT CAST(0 AS BIT) AS success, 'Penugasan tidak ditemukan atau Anda tidak memiliki akses.' AS message;
            RETURN;
        END

        IF @existing_status = @NEW_STATUS
        BEGIN
            SELECT CAST(0 AS BIT) AS success, 'Status penugasan sudah berada pada tahap ini.' AS message;
            RETURN;
        END

        SET @new_version = ISNULL(@existing_ver, 0) + 1;

        -- Insert log audit history
        INSERT INTO ALDA_PENUGASAN_HISTORY
            ( PENUGASAN_ID, CONTRACT_NO, CHANGE_TYPE, CHANGE_REASON, CHANGED_AT, CHANGED_BY,
              STATUS_BEFORE, STATUS_AFTER, ASSIGN_VERSION_BEFORE, ASSIGN_VERSION_AFTER,
              PIC_NIK_BEFORE, PIC_NIK_AFTER, CONTRACT_STATUS_SNAPSHOT, AMOUNT_TO_BE_PAID )
        SELECT 
            PENUGASAN_ID, CONTRACT_NO, 'UPDATE_STATUS', 'PIC merubah status via Aplikasi', GETDATE(), @PIC_NIK,
            STATUS, @NEW_STATUS, ASSIGN_VERSION, @new_version,
            PIC_NIK, PIC_NIK, CONTRACT_STATUS_SNAPSHOT, AMOUNT_TO_BE_PAID
        FROM ALDA_PENUGASAN WITH (NOLOCK)
        WHERE PENUGASAN_ID = @PENUGASAN_ID;

        -- Update main table
        UPDATE ALDA_PENUGASAN
        SET STATUS = @NEW_STATUS,
            ASSIGN_VERSION = @new_version,
            UPDATED_AT = GETDATE(),
            UPDATED_BY = @PIC_NIK
        WHERE PENUGASAN_ID = @PENUGASAN_ID;

        SELECT CAST(1 AS BIT) AS success, 'Status berhasil diperbarui.' AS message;

    END TRY
    BEGIN CATCH
        SELECT CAST(0 AS BIT) AS success, ERROR_MESSAGE() AS message;
    END CATCH;
END;
GO