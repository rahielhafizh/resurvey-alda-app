ALTER PROCEDURE [dbo].[SP_LOGIN_RESURVEY_ALDA]
    @p_NIK VARCHAR(50),
    @p_Password VARCHAR(255)
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @v_Status INT;
    DECLARE @v_Message VARCHAR(255);
    DECLARE @v_Nama VARCHAR(200);
    DECLARE @v_IsActive BIT;

    SELECT 
        @v_Nama = [NAMA],
        @v_IsActive = [IS_ACTIVE]
    FROM [dbo].[MASTER_ALDA_PIC]
    WHERE [NIK] = @p_NIK;

    IF @@ROWCOUNT = 0
    BEGIN
        SET @v_Status = -1;
        SET @v_Message = 'NIK tidak terdaftar di sistem.';
        SELECT @v_Status AS LoginStatus, @v_Message AS Message, NULL AS NIK, NULL AS NAMA;
        RETURN;
    END

    IF @v_IsActive = 0
    BEGIN
        SET @v_Status = -1;
        SET @v_Message = 'Akun Anda sudah tidak aktif. Silakan hubungi Administrator.';
        SELECT @v_Status AS LoginStatus, @v_Message AS Message, NULL AS NIK, NULL AS NAMA;
        RETURN;
    END

    IF @p_Password = 'user.100'
    BEGIN
        SET @v_Status = 1;
        SET @v_Message = 'Login Berhasil.';
        SELECT @v_Status AS LoginStatus, @v_Message AS Message, @p_NIK AS NIK, @v_Nama AS NAMA;
    END
    ELSE
    BEGIN
        SET @v_Status = 0;
        SET @v_Message = 'Password yang Anda masukkan salah.';
        SELECT @v_Status AS LoginStatus, @v_Message AS Message, NULL AS NIK, NULL AS NAMA;
    END
END
GO