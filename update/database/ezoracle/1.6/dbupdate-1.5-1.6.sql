CREATE OR replace FUNCTION bitor( x IN NUMBER, y IN NUMBER )
RETURN NUMBER  AS
--
-- Return an bitwise 'or' value of the input arguments.
--
BEGIN
    RETURN x + y - bitand(x,y);
END bitor;
/

-- The following modifications are changes from the MySQL to Oracle schema that
-- are part of the database init scripts in eZ Oracle 1.6 but not in 1.5

ALTER TABLE ezurl MODIFY (url varchar2(3000));
ALTER TABLE ezrss_import MODIFY (url varchar2(3100));
ALTER TABLE ezrss_import MODIFY (import_description NULL);
ALTER TABLE ezgeneral_digest_user_settings MODIFY (time NULL);