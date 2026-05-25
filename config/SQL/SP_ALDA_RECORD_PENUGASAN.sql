USE [MOBILE_COLLECTION]
GO

/****** Object:  StoredProcedure [dbo].[SP_ALDA_RECORD_PENUGASAN]    Script Date: 19/05/2026 ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO


ALTER PROCEDURE [dbo].[SP_ALDA_RECORD_PENUGASAN]
(
    @BRANCH_ID     VARCHAR(10),
    @NOMOR_KONTRAK VARCHAR(50)  = '',
    @PIC_NIK       VARCHAR(50)  = '',
    @DATE_FROM     DATETIME     = NULL,
    @DATE_TO       DATETIME     = NULL
)
AS
BEGIN
    SET NOCOUNT ON;

    SELECT
          P.CONTRACT_NO                                                          AS NOMOR_KONTRAK,
          P.PIC_NIK                                                              AS PIC_NIK,
          P.PIC_NAME                                                             AS PIC,
          ISNULL(P.PIC_JABATAN,        '-')                                      AS JABATAN_PIC,
          ISNULL(P.PIC_LOKASI_FISIK,   '-')                                      AS LOKASI_PIC,
          ISNULL(P.PIC_CABANG,         '-')                                      AS CABANG,
          ISNULL(P.PORTFOLIO,          A.PORTFOLIO)                              AS PORTFOLIO,
          ISNULL(P.TYPE_KENDARAAN,     A.TYPE_KENDARAAN)                         AS UNIT,
          ISNULL(P.AMOUNT_TO_BE_PAID,  A.AMOUNT_TO_BE_PAID)                      AS AMOUNT_TO_BE_PAID,
          ISNULL(P.CUSTOMER_NAME,      A.CUSTOMER_NAME)                          AS CUSTOMER_NAME,
          P.STATUS                                                               AS ASSIGN_STATUS,
          CONVERT(VARCHAR(10), ISNULL(P.UPDATED_AT, P.CREATED_AT), 105)         AS CREATED_DATE,
          P.CREATED_BY                                                           AS CREATED_BY
    FROM  ALDA_PENUGASAN AS P WITH (NOLOCK)
        INNER JOIN MASTER_ALDA AS A WITH (NOLOCK)
            ON  A.NOMOR_KONTRAK = P.CONTRACT_NO
            AND A.BRANCH_ID     = @BRANCH_ID
    WHERE P.STATUS NOT IN ('CANCELLED')
      AND (@NOMOR_KONTRAK = '' OR P.CONTRACT_NO                  = @NOMOR_KONTRAK)
      AND (@PIC_NIK       = '' OR REPLACE(P.PIC_NIK, '.', '')    = REPLACE(@PIC_NIK, '.', ''))
      AND (@DATE_FROM IS NULL   OR P.CREATED_AT                 >= @DATE_FROM)
      AND (@DATE_TO   IS NULL   OR P.CREATED_AT                  < DATEADD(DAY, 1, @DATE_TO))
    ORDER BY P.CREATED_AT DESC;

END;
GO