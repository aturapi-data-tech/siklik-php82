-- ============================================================
-- LANGKAH 5 — Type / function / procedure / package PL/SQL
-- Dibuat  : php artisan siklik:ddl-create  (2026-09-12 10:09)
-- Sumber  : data dictionary schema yang terhubung saat generate
-- Dibutuhkan view tertentu (mis. STRING_AGG). "created with compilation errors" tidak menghentikan skrip — periksa SHOW ERRORS.
-- ============================================================
SET DEFINE OFF
SET SQLBLANKLINES ON
SET ECHO ON
WHENEVER SQLERROR EXIT SQL.SQLCODE

-- Unit PL/SQL (18) — terminator "/"

-- TYPE T_STRING_AGG
CREATE OR REPLACE TYPE "T_STRING_AGG"                                                                          AS OBJECT
(
  g_string  VARCHAR2(32767),

  STATIC FUNCTION ODCIAggregateInitialize(sctx  IN OUT  t_string_agg)
    RETURN NUMBER,

  MEMBER FUNCTION ODCIAggregateIterate(self   IN OUT  t_string_agg,
                                       value  IN      VARCHAR2 )
     RETURN NUMBER,

  MEMBER FUNCTION ODCIAggregateTerminate(self         IN   t_string_agg,
                                         returnValue  OUT  VARCHAR2,
                                         flags        IN   NUMBER)
    RETURN NUMBER,

  MEMBER FUNCTION ODCIAggregateMerge(self  IN OUT  t_string_agg,
                                     ctx2  IN      t_string_agg)
    RETURN NUMBER
);
/
SHOW ERRORS

-- PACKAGE SOAP_API
CREATE OR REPLACE PACKAGE soap_api AS
-- --------------------------------------------------------------------------
-- Name         : https://oracle-base.com/dba/miscellaneous/soap_api.sql
-- Author       : Tim Hall
-- Description  : SOAP related functions for consuming web services.
-- License      : Free for personal and commercial use.
--                You can amend the code, but leave existing the headers, current
--                amendments history and links intact.
--                Copyright and disclaimer available here:
--                https://oracle-base.com/misc/site-info.php#copyright
-- Ammedments   :
--   When         Who       What
--   ===========  ========  =================================================
--   04-OCT-2003  Tim Hall  Initial Creation
--   23-FEB-2006  Tim Hall  Parameterized the "soap" envelope tags.
--   25-MAY-2012  Tim Hall  Added debug switch.
--   29-MAY-2012  Tim Hall  Allow parameters to have no type definition.
--                          Change the default envelope tag to "soap".
--                          add_complex_parameter: Include parameter XML manually.
--   24-MAY-2014  Tim Hall  Added license information.
-- --------------------------------------------------------------------------

TYPE t_request IS RECORD (
  method        VARCHAR2(256),
  namespace     VARCHAR2(256),
  body          VARCHAR2(32767),
  envelope_tag  VARCHAR2(30)
);

TYPE t_response IS RECORD
(
  doc           XMLTYPE,
  envelope_tag  VARCHAR2(30)
);

FUNCTION new_request(p_method        IN  VARCHAR2,
                     p_namespace     IN  VARCHAR2,
                     p_envelope_tag  IN  VARCHAR2 DEFAULT 'soap')
  RETURN t_request;


PROCEDURE add_parameter(p_request  IN OUT NOCOPY  t_request,
                        p_name     IN             VARCHAR2,
                        p_value    IN             VARCHAR2,
                        p_type     IN             VARCHAR2 := NULL);

PROCEDURE add_complex_parameter(p_request  IN OUT NOCOPY  t_request,
                                p_xml      IN             VARCHAR2);

FUNCTION invoke(p_request  IN OUT NOCOPY  t_request,
                p_url      IN             VARCHAR2,
                p_action   IN             VARCHAR2)
  RETURN t_response;

FUNCTION get_return_value(p_response   IN OUT NOCOPY  t_response,
                          p_name       IN             VARCHAR2,
                          p_namespace  IN             VARCHAR2)
  RETURN VARCHAR2;

PROCEDURE debug_on;
PROCEDURE debug_off;

END soap_api;
/
SHOW ERRORS

-- TYPE BODY T_STRING_AGG
CREATE OR REPLACE TYPE BODY t_string_agg IS
  STATIC FUNCTION ODCIAggregateInitialize(sctx  IN OUT  t_string_agg)
    RETURN NUMBER IS
  BEGIN
    sctx := t_string_agg(NULL);
    RETURN ODCIConst.Success;
  END;

  MEMBER FUNCTION ODCIAggregateIterate(self   IN OUT  t_string_agg,
                                       value  IN      VARCHAR2 )
    RETURN NUMBER IS
  BEGIN
    SELF.g_string := self.g_string || ',' || value;
    RETURN ODCIConst.Success;
  END;

  MEMBER FUNCTION ODCIAggregateTerminate(self         IN   t_string_agg,
                                         returnValue  OUT  VARCHAR2,
                                         flags        IN   NUMBER)
    RETURN NUMBER IS
  BEGIN
    returnValue := RTRIM(LTRIM(SELF.g_string, ','), ',');
    RETURN ODCIConst.Success;
  END;

  MEMBER FUNCTION ODCIAggregateMerge(self  IN OUT  t_string_agg,
                                     ctx2  IN      t_string_agg)
    RETURN NUMBER IS
  BEGIN
    SELF.g_string := SELF.g_string || ',' || ctx2.g_string;
    RETURN ODCIConst.Success;
  END;
END;
/
SHOW ERRORS

-- FUNCTION ADD_NUMBERS
CREATE OR REPLACE FUNCTION add_numbers (p_int_1  IN  NUMBER,
                                        p_int_2  IN  NUMBER)
  RETURN NUMBER
AS
  l_request   soap_api.t_request;
  l_response  soap_api.t_response;
  l_return    VARCHAR2(32767);

  l_url          VARCHAR2(32767);
  l_namespace    VARCHAR2(32767);
  l_method       VARCHAR2(32767);
  l_soap_action  VARCHAR2(32767);
  l_result_name  VARCHAR2(32767);
BEGIN
  l_url         := 'http://oracle-base.com/webservices/server.php';
  l_namespace   := 'xmlns="http://oracle-base.com/webservices/"';
  l_method      := 'ws_add';
  l_soap_action := 'http://oracle-base.com/webservices/server.php/ws_add';
  l_result_name := 'return';

  l_request := soap_api.new_request(p_method       => l_method,
                                    p_namespace    => l_namespace);

  soap_api.add_parameter(p_request => l_request,
                         p_name    => 'int1',
                         p_type    => 'xsd:integer',
                         p_value   => p_int_1);

  soap_api.add_parameter(p_request => l_request,
                         p_name    => 'int2',
                         p_type    => 'xsd:integer',
                         p_value   => p_int_2);

  l_response := soap_api.invoke(p_request => l_request,
                                p_url     => l_url,
                                p_action  => l_soap_action);

  l_return := soap_api.get_return_value(p_response  => l_response,
                                        p_name      => l_result_name,
                                        p_namespace => NULL);

  RETURN l_return;
END;
/
SHOW ERRORS

-- FUNCTION CUSTOM_HASH
CREATE OR REPLACE function custom_hash (p_username in varchar2, p_password in varchar2)
					  return varchar2
					  is
					    l_password varchar2(4000);
					    l_salt varchar2(4000) := 'JCGDHRBVDI1JIGZ7XZKL4UBGTQFZDV';
					  begin
					  
					 -- This function should be wrapped, as the hash algorhythm is exposed here.
					 -- You can change the value of l_salt or the method of which to call the 
					 -- DBMS_OBFUSCATOIN toolkit, but you much reset all of your passwords
					 -- if you choose to do this.
					  
					  l_password := utl_raw.cast_to_raw(dbms_obfuscation_toolkit.md5
					    (input_string => p_password || substr(l_salt,10,13) || p_username || 
					      substr(l_salt, 4,10)));
					  return l_password;
					  end;
/
SHOW ERRORS

-- FUNCTION GET_BOXSTOCK
CREATE OR REPLACE FUNCTION GET_BOXSTOCK(stock number,stock_per_box number) RETURN VARCHAR2 IS
--# d1 = tanggal awal
--# d2 = tanggal akhir
vget_box number;
vget_item number;
xresult varchar2(100);
BEGIN

if stock_per_box=0 or stock_per_box is null then
xresult:='0'||' BOX / '||stock||' ITEM';
else
if stock>=0 then
vget_box := floor((stock/ stock_per_box));
vget_item := stock-(stock_per_box*vget_box);
xresult:=vget_box||' BOX / '||vget_item||' ITEM';
else
vget_box := floor((abs(stock)/ stock_per_box));
vget_item := abs(stock)-(stock_per_box*vget_box);

xresult:='-'||vget_box||' BOX / '||'-'||vget_item||' ITEM';
end if;	
end if;
RETURN(xresult);
END GET_BOXSTOCK;
/
SHOW ERRORS

-- FUNCTION GET_CONVERSION_RATE
CREATE OR REPLACE function get_conversion_rate
( p_country1 in varchar2 default 'us'
, p_country2 in varchar2 default 'us'
)
return varchar2
as
  soap_request varchar2(30000);
  soap_respond varchar2(30000);
  http_req utl_http.req;
  http_resp utl_http.resp;
  resp XMLType;
begin
  soap_request:= '<?xml version = "1.0" encoding = "UTF-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
   <SOAP-ENV:Body>
      <ns1:getRate xmlns:ns1="urn:xmethods-CurrencyExchange" SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
         <country1 xsi:type="xsd:string">'||p_country1||'</country1>
         <country2 xsi:type="xsd:string">'||p_country2||'</country2>
      </ns1:getRate>
   </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
';
  http_req:= utl_http.begin_request
             ( 'http://services.xmethods.net:80/soap'
             , 'POST'
             , 'HTTP/1.1'
             );
  utl_http.set_header(http_req, 'Content-Type', 'text/xml');
  utl_http.set_header(http_req, 'Content-Length', length(soap_request));
  utl_http.set_header(http_req, 'SOAPAction', '');
  utl_http.write_text(http_req, soap_request);
  http_resp:= utl_http.get_response(http_req);
  utl_http.read_text(http_resp, soap_respond);
  utl_http.end_response(http_resp);
  -- Create an XMLType variable containing the Response XML
  resp:= XMLType.createXML(soap_respond);
  -- extract from the XMLType Resp the child-nodes of the <soap:Body> element
  resp:= resp.extract('/soap:Envelope/soap:Body/child::node()'
                     , 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"'
                     );
  -- extract from the XMLType Resp the text() nodes from the n:getRateResponse/Result element
  resp:= resp.extract('n:getRateResponse/Result/text()','xmlns:n="urn:xmethods-CurrencyExchange"');
  return resp.getClobVal();
end;
/
SHOW ERRORS

-- FUNCTION GET_DURATION
CREATE OR REPLACE FUNCTION GET_DURATION(d1 number) RETURN VARCHAR2 IS
--# d1 = tanggal awal
--# d2 = tanggal akhir
tmpvar VARCHAR2(100);
durdays NUMBER(20, 10); -- days between two dates
durhrs BINARY_INTEGER; -- completed hours
durmin BINARY_INTEGER; -- completed minutes
dursec BINARY_INTEGER; -- completed second
durhari PLS_INTEGER;
jhrs number;
jmin number;
thrs number;
BEGIN
durdays := d1;

durhari := TRUNC(d1);

durhrs := MOD(TRUNC(24 * durdays), 24);
durmin := MOD(TRUNC(durdays * 1440), 60);
dursec := MOD(TRUNC(durdays * 86400), 60);

tmpvar := durhari || ':' || durhrs || ':' || durmin || ':' ||dursec || '';

jhrs :=(to_number(durhari)*24)+to_number(durhrs||'.'||lpad(durmin,2,'0'));
thrs :=jhrs;
RETURN(thrs);
END get_duration;
/
SHOW ERRORS

-- FUNCTION ZRIGHT
CREATE OR REPLACE FUNCTION zright(iChar VARCHAR2,jumlah NUMBER) RETURN VARCHAR IS
 Vpanjang  NUMBER;
 Vchar_Kanan VARCHAR2(100);
BEGIN
  Vpanjang:=Length(iChar)+1-jumlah;
  Vchar_Kanan:=SUBSTR(iChar,Vpanjang,jumlah);
  RETURN Vchar_Kanan;
END;
/
SHOW ERRORS

-- FUNCTION ZMID
CREATE OR REPLACE FUNCTION ZMID(CStr IN VARCHAR2, STR IN INTEGER, LEN IN NUMBER DEFAULT 32767) RETURN VARCHAR2 IS
     iMID VARCHAR2(32767);
BEGIN
     If ( STR > Length(CStr) ) Then
         RETURN( NULL );
     END IF;
     iMID := SUBSTR( CStr, STR, len );
     RETURN ( iMID );
END;
/
SHOW ERRORS

-- FUNCTION ORACLE_TERBILANG
CREATE OR REPLACE function ORACLE_TERBILANG(angka number) return varchar2 is

  strJmlHuruf     number;
  terbilang       varchar2(32767);
  intPecahan      varchar2(32767);
  strPecahan      varchar2(32767);
  urai            varchar2(32767);
  sMinus          varchar2(32767);
  strTot          varchar2(32767);
  x               varchar2(32767);
  y               integer;
  z               integer;
  Bil1            varchar2(32767);
  Bil2            varchar2(32767);
  pNumber         varchar2(32767);

 begin

   /*Cek Panjang input Angka*/
    pNumber :=length(angka);

    if substr(angka,1,1) <> '-' then
       if 16 <= pNumber then terbilang := ''; return(terbilang); end if;
    Else
       if 17 <= pNumber then terbilang := ''; return(terbilang); end if;
    End If;

   /*Cek Awal NOL*/
   If angka is Null or angka =0 Then terbilang := 'NOL';
      return(terbilang);
   End If;

       strJmlHuruf := LTrim((angka)); intPecahan  := 0;

  /*Cek Awal Minus*/
    If InStr(strJmlHuruf, '-') > 0 Then
            sMinus := 'MINUS ';
            terbilang := ZRight(strJmlHuruf, length(strJmlHuruf) - 1);
            strTot := terbilang; strJmlHuruf := terbilang;
    End If;

    /*Sementara Tdak ada pecahan :D*/
    If (intPecahan = 0) Then strPecahan := ''; End If;

    X := 0;
    Y := 0;
    Urai := '';

    /*::::::::::::LOOP::::::::::::*/
    While (X < length(strJmlHuruf)) Loop
                    X := X + 1;
                    strTot := ZMID(strJmlHuruf, X, 1);
                    Y := Y + (strTot);
                    z := length(strJmlHuruf) - X + 1;

    /*::::::::::::::::::::::::::Select Case Val(strTot)::::::::::::::::::::::::::*/
            if (strTot) = 1 then

                /*:::::::::::::::::::::::::::*/
                If (z = 1 Or z = 7 Or z = 10 Or z = 13) Then Bil1 := 'SATU ';

                ElsIf (z = 4) Then
                     If (X = 1) Then Bil1 := 'SE';
                        Else Bil1 := 'SATU ';
                    End If;

                ElsIf (z = 2 Or z = 5 Or z = 8 Or z = 11 Or z = 14 ) Then
                    X := X + 1;
                    strTot := ZMid(strJmlHuruf, X, 1);
                    z := length(strJmlHuruf) - X + 1;
                    Bil2 := '';

                       if  strTot = 0 then Bil1 := 'SEPULUH ';
                             ElsIf strTot = 1 then Bil1 := 'SEBELAS ';
                             ElsIf strTot = 2 then Bil1 := 'DUA BELAS ';
                             ElsIf strTot = 3 then Bil1 := 'TIGA BELAS ';
                             ElsIf strTot = 4 then Bil1 := 'EMPAT BELAS ';
                             ElsIf strTot = 5 then Bil1 := 'LIMA BELAS ';
                             ElsIf strTot = 6 then Bil1 := 'ENAM BELAS ';
                             ElsIf strTot = 7 then Bil1 := 'TUJUH BELAS ';
                             ElsIf strTot = 8 then Bil1 := 'DELAPAN BELAS ';
                             else Bil1 := 'SEMBILAN BELAS ';
                       end if;
                 else
                  Bil1 := 'SE';
              end if;
              /*:::::::::::::::::::::::::::*/

              elsif strTot = 2 then Bil1 := 'DUA ';
              elsif strTot = 3 then Bil1 := 'TIGA ';
              elsif strTot = 4 then Bil1 := 'EMPAT ';
              elsif strTot = 5 then Bil1 := 'LIMA ';
              elsif strTot = 6 then Bil1 := 'ENAM ';
              elsif strTot = 7 then Bil1 := 'TUJUH ';
              elsif strTot = 8 then Bil1 := 'DELAPAN ';
              elsif strTot = 9 then Bil1 := 'SEMBILAN ';
              else Bil1 := '';
            end if;
    /*::::::::::::::::::::::::::END Select Case Val(strTot)::::::::::::::::::::::::::*/

                If ((strTot) > 0) Then
                    If (z = 2 Or z = 5 Or z = 8 Or z = 11 Or z = 14) Then      Bil2 := 'PULUH ';
                      ElsIf (z = 3 Or z = 6 Or z = 9 Or z = 12 Or z = 15) Then Bil2 := 'RATUS ';
                      Else Bil2 := '';
                    End If;
                Else       Bil2 := '';
                End If;

                If (Y > 0) Then
                  /*::::::Select Case z:::::::*/
                   if z = 4 then        Bil2 := Bil2||'RIBU ';   Y := 0;
                      elsif z = 7  then Bil2 := Bil2||'JUTA ';   Y := 0;
                      elsif z = 10 then Bil2 := Bil2||'MILYAR '; Y := 0;
                      elsif z = 13 then Bil2 := Bil2||'TRILYUN ';Y := 0;
                   end if;
                End If;

     Urai := Urai||Bil1||Bil2;
    end loop;
    /*::::::::::::END LOOP::::::::::::*/

       Urai := sMinus||Urai||strPecahan;
        If (intPecahan = 0) Then
           Terbilang := Urai||'RUPIAH ';
        End If;
  return(terbilang);

end ORACLE_TERBILANG;
/
SHOW ERRORS

-- FUNCTION STRING_AGG
CREATE OR REPLACE FUNCTION string_agg (p_input VARCHAR2)
RETURN VARCHAR2
PARALLEL_ENABLE AGGREGATE USING t_string_agg;
/
SHOW ERRORS

-- FUNCTION TK_CRYPT
CREATE OR REPLACE function         TK_crypt( p_str in varchar2 )
 return raw
     as
         l_data  varchar2(4000);
     begin
         l_data := rpad( p_str, (trunc(length(p_str)/8)+1)*8, chr(0) );
             return dbms_obfuscation_toolkit.DESEncrypt
                ( input => utl_raw.cast_to_raw(l_data),
                             key => utl_raw.cast_to_raw('MagicKey') );
end;
/
SHOW ERRORS

-- FUNCTION TK_DECRYPT
CREATE OR REPLACE function         TK_decrypt( p_str in raw ) return
   varchar2
   as
   begin
         return utl_raw.cast_to_varchar2(
               dbms_obfuscation_toolkit.DESdecrypt
               ( input => p_str,
                 key   => utl_raw.cast_to_raw('MagicKey') ) );
 end;
/
SHOW ERRORS

-- PROCEDURE RESET_SEQ
CREATE OR REPLACE procedure reset_seq( p_seq_name in varchar2 )
is
    l_val number;
begin
    execute immediate
    'select ' || p_seq_name || '.nextval from dual' INTO l_val;

    execute immediate
    'alter sequence ' || p_seq_name || ' increment by -' || l_val ||
                                                          ' minvalue 0';

    execute immediate
    'select ' || p_seq_name || '.nextval from dual' INTO l_val;

    execute immediate
    'alter sequence ' || p_seq_name || ' increment by 1 minvalue 0';
end;
/
SHOW ERRORS

-- PROCEDURE TK_BACKUP_DB
CREATE OR REPLACE procedure         tk_backup_db is
vbackup_db varchar2(4000);
 begin
 --oracle 10g base on UBUNTU
 select tk_decrypt(backup_db) into vbackup_db from dimst_identitases;
    dbms_scheduler.create_job (job_name    => 'myjob',
                               job_type    => 'executable',
                               job_action  => '/bin/sh',
                               number_of_arguments => 2,
                               auto_drop   => true);
    dbms_scheduler.set_job_argument_value ('myjob', 1,'-c');
    dbms_scheduler.set_job_argument_value ('myjob', 2,vbackup_db);
    dbms_scheduler.run_job ('myjob');
 end;
/
SHOW ERRORS

-- PROCEDURE TK_DROP_MYJOB_BACKUP_DB
CREATE OR REPLACE procedure         tk_drop_myjob_backup_db is
begin
dbms_scheduler.drop_job(job_name => 'myjob');
end;
/
SHOW ERRORS

-- PACKAGE BODY SOAP_API
CREATE OR REPLACE PACKAGE BODY soap_api AS
-- --------------------------------------------------------------------------
-- Name         : https://oracle-base.com/dba/miscellaneous/soap_api.sql
-- Author       : Tim Hall
-- Description  : SOAP related functions for consuming web services.
-- License      : Free for personal and commercial use.
--                You can amend the code, but leave existing the headers, current
--                amendments history and links intact.
--                Copyright and disclaimer available here:
--                https://oracle-base.com/misc/site-info.php#copyright
-- Ammedments   :
--   When         Who       What
--   ===========  ========  =================================================
--   04-OCT-2003  Tim Hall  Initial Creation
--   23-FEB-2006  Tim Hall  Parameterized the "soap" envelope tags.
--   25-MAY-2012  Tim Hall  Added debug switch.
--   29-MAY-2012  Tim Hall  Allow parameters to have no type definition.
--                          Change the default envelope tag to "soap".
--                          add_complex_parameter: Include parameter XML manually.
--   24-MAY-2014  Tim Hall  Added license information.
-- --------------------------------------------------------------------------

g_debug  BOOLEAN := FALSE;

PROCEDURE show_envelope(p_env     IN  VARCHAR2,
                        p_heading IN  VARCHAR2 DEFAULT NULL);



-- ---------------------------------------------------------------------
FUNCTION new_request(p_method        IN  VARCHAR2,
                     p_namespace     IN  VARCHAR2,
                     p_envelope_tag  IN  VARCHAR2 DEFAULT 'soap')
  RETURN t_request AS
-- ---------------------------------------------------------------------
  l_request  t_request;
BEGIN
  l_request.method       := p_method;
  l_request.namespace    := p_namespace;
  l_request.envelope_tag := p_envelope_tag;
  RETURN l_request;
END;
-- ---------------------------------------------------------------------



-- ---------------------------------------------------------------------
PROCEDURE add_parameter(p_request  IN OUT NOCOPY  t_request,
                        p_name     IN             VARCHAR2,
                        p_value    IN             VARCHAR2,
                        p_type     IN             VARCHAR2 := NULL) AS
-- ---------------------------------------------------------------------
BEGIN
  IF p_type IS NULL THEN
    p_request.body := p_request.body||'<'||p_name||'>'||p_value||'</'||p_name||'>';
  ELSE
    p_request.body := p_request.body||'<'||p_name||' xsi:type="'||p_type||'">'||p_value||'</'||p_name||'>';
  END IF;
END;
-- ---------------------------------------------------------------------



-- ---------------------------------------------------------------------
PROCEDURE add_complex_parameter(p_request  IN OUT NOCOPY  t_request,
                                p_xml      IN             VARCHAR2) AS
-- ---------------------------------------------------------------------
BEGIN
  p_request.body := p_request.body||p_xml;
END;
-- ---------------------------------------------------------------------



-- ---------------------------------------------------------------------
PROCEDURE generate_envelope(p_request  IN OUT NOCOPY  t_request,
		                        p_env      IN OUT NOCOPY  VARCHAR2) AS
-- ---------------------------------------------------------------------
BEGIN
  p_env := '<'||p_request.envelope_tag||':Envelope xmlns:'||p_request.envelope_tag||'="http://schemas.xmlsoap.org/soap/envelope/" ' ||
               'xmlns:xsi="http://www.w3.org/1999/XMLSchema-instance" xmlns:xsd="http://www.w3.org/1999/XMLSchema">' ||
             '<'||p_request.envelope_tag||':Body>' ||
               '<'||p_request.method||' '||p_request.namespace||' '||p_request.envelope_tag||':encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">' ||
                   p_request.body ||
               '</'||p_request.method||'>' ||
             '</'||p_request.envelope_tag||':Body>' ||
           '</'||p_request.envelope_tag||':Envelope>';
END;
-- ---------------------------------------------------------------------



-- ---------------------------------------------------------------------
PROCEDURE show_envelope(p_env     IN  VARCHAR2,
                        p_heading IN  VARCHAR2 DEFAULT NULL) AS
-- ---------------------------------------------------------------------
  i      PLS_INTEGER;
  l_len  PLS_INTEGER;
BEGIN
  IF g_debug THEN
    IF p_heading IS NOT NULL THEN
      DBMS_OUTPUT.put_line('*****' || p_heading || '*****');
    END IF;

    i := 1; l_len := LENGTH(p_env);
    WHILE (i <= l_len) LOOP
      DBMS_OUTPUT.put_line(SUBSTR(p_env, i, 60));
      i := i + 60;
    END LOOP;
  END IF;
END;
-- ---------------------------------------------------------------------



-- ---------------------------------------------------------------------
PROCEDURE check_fault(p_response IN OUT NOCOPY  t_response) AS
-- ---------------------------------------------------------------------
  l_fault_node    XMLTYPE;
  l_fault_code    VARCHAR2(256);
  l_fault_string  VARCHAR2(32767);
BEGIN
  l_fault_node := p_response.doc.extract('/'||p_response.envelope_tag||':Fault',
                                         'xmlns:'||p_response.envelope_tag||'="http://schemas.xmlsoap.org/soap/envelope/');
  IF (l_fault_node IS NOT NULL) THEN
    l_fault_code   := l_fault_node.extract('/'||p_response.envelope_tag||':Fault/faultcode/child::text()',
                                           'xmlns:'||p_response.envelope_tag||'="http://schemas.xmlsoap.org/soap/envelope/').getstringval();
    l_fault_string := l_fault_node.extract('/'||p_response.envelope_tag||':Fault/faultstring/child::text()',
                                           'xmlns:'||p_response.envelope_tag||'="http://schemas.xmlsoap.org/soap/envelope/').getstringval();
    RAISE_APPLICATION_ERROR(-20000, l_fault_code || ' - ' || l_fault_string);
  END IF;
END;
-- ---------------------------------------------------------------------



-- ---------------------------------------------------------------------
FUNCTION invoke(p_request IN OUT NOCOPY  t_request,
                p_url     IN             VARCHAR2,
                p_action  IN             VARCHAR2)
  RETURN t_response AS
-- ---------------------------------------------------------------------
  l_envelope       VARCHAR2(32767);
  l_http_request   UTL_HTTP.req;
  l_http_response  UTL_HTTP.resp;
  l_response       t_response;
BEGIN
  generate_envelope(p_request, l_envelope);
  show_envelope(l_envelope, 'Request');
  l_http_request := UTL_HTTP.begin_request(p_url, 'POST','HTTP/1.1');
  UTL_HTTP.set_header(l_http_request, 'Content-Type', 'text/xml');
  UTL_HTTP.set_header(l_http_request, 'Content-Length', LENGTH(l_envelope));
  UTL_HTTP.set_header(l_http_request, 'SOAPAction', p_action);
  UTL_HTTP.write_text(l_http_request, l_envelope);
  l_http_response := UTL_HTTP.get_response(l_http_request);
  UTL_HTTP.read_text(l_http_response, l_envelope);
  UTL_HTTP.end_response(l_http_response);
  show_envelope(l_envelope, 'Response');
  l_response.doc := XMLTYPE.createxml(l_envelope);
  l_response.envelope_tag := p_request.envelope_tag;
  l_response.doc := l_response.doc.extract('/'||l_response.envelope_tag||':Envelope/'||l_response.envelope_tag||':Body/child::node()',
                                           'xmlns:'||l_response.envelope_tag||'="http://schemas.xmlsoap.org/soap/envelope/"');
  check_fault(l_response);
  RETURN l_response;
END;
-- ---------------------------------------------------------------------



-- ---------------------------------------------------------------------
FUNCTION get_return_value(p_response   IN OUT NOCOPY  t_response,
                          p_name       IN             VARCHAR2,
                          p_namespace  IN             VARCHAR2)
  RETURN VARCHAR2 AS
-- ---------------------------------------------------------------------
BEGIN
  RETURN p_response.doc.extract('//'||p_name||'/child::text()',p_namespace).getstringval();
END;
-- ---------------------------------------------------------------------



-- ---------------------------------------------------------------------
PROCEDURE debug_on AS
-- ---------------------------------------------------------------------
BEGIN
  g_debug := TRUE;
END;
-- ---------------------------------------------------------------------



-- ---------------------------------------------------------------------
PROCEDURE debug_off AS
-- ---------------------------------------------------------------------
BEGIN
  g_debug := FALSE;
END;
-- ---------------------------------------------------------------------

END soap_api;
/
SHOW ERRORS
