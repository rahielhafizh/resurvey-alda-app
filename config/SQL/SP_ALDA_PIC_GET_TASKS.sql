ALTER PROCEDURE [dbo].[SP_ALDA_PIC_GET_TASKS]
(
    @PIC_NIK VARCHAR(50),
    @STATUS  VARCHAR(20)
)
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @normalised_nik VARCHAR(50);
    SET @normalised_nik = REPLACE(@PIC_NIK, '.', '');

    SELECT
        P.PENUGASAN_ID,
        P.CONTRACT_NO,
        ISNULL(P.CUSTOMER_NAME,  A.CUSTOMER_NAME)  AS CUSTOMER_NAME,
        ISNULL(P.LEGAL_ADDRESS,  A.LEGAL_ADDRESS)   AS LEGAL_ADDRESS,
        ISNULL(P.CUSTOMER_PHONE, A.CUSTOMER_PHONE)  AS CUSTOMER_PHONE,

        LTRIM(RTRIM(
            ISNULL(P.MERK_KENDARAAN, '')
            + CASE
                WHEN P.TYPE_KENDARAAN IS NOT NULL AND LTRIM(RTRIM(P.TYPE_KENDARAAN)) <> ''
                THEN ' ' + LTRIM(RTRIM(P.TYPE_KENDARAAN))
                ELSE ''
              END
            + CASE
                WHEN P.TAHUN_KENDARAAN IS NOT NULL
                THEN ' ' + CAST(P.TAHUN_KENDARAAN AS VARCHAR(4))
                ELSE ''
              END
        ))                                          AS KENDARAAN,

        ISNULL(P.AMOUNT_TO_BE_PAID, A.AMOUNT_TO_BE_PAID) AS AMOUNT_TO_BE_PAID,
        P.STATUS,
        P.CREATED_AT                                AS TANGGAL_ASSIGN,
        P.UPDATED_AT,
        P.ASSIGN_VERSION,
        P.NOTES
    FROM  ALDA_PENUGASAN AS P WITH (NOLOCK)
        INNER JOIN MASTER_ALDA AS A WITH (NOLOCK)
            ON  A.NOMOR_KONTRAK = P.CONTRACT_NO
    WHERE REPLACE(P.PIC_NIK, '.', '') = @normalised_nik
      AND P.STATUS = @STATUS
    ORDER BY P.CREATED_AT DESC;

END;
GO