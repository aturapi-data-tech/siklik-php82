-- Backup DDL objek INVALID sebelum di-drop (dbms_metadata.get_ddl) — untuk rollback.
-- Jalankan tiap blok diakhiri baris '/'.

-- ==== FUNCTION CUSTOM_AUTH ====
CREATE OR REPLACE FUNCTION "SIKLIK"."CUSTOM_AUTH" (p_username in VARCHAR2, p_password in VARCHAR2)
					  return BOOLEAN
					  is
					    l_password varchar2(4000);
					    l_stored_password varchar2(4000);
					    l_expires_on date;
					    l_count number;
					  begin
					  -- First, check to see if the user is in the user table
					  select count(*) into l_count from demo_users where user_name = p_username;
					  if l_count > 0 then
					    -- First, we fetch the stored hashed password & expire date 
					    select password, expires_on into l_stored_password, l_expires_on 
					     from demo_users where user_name = p_username;
					 
					    -- Next, we check to see if the user's account is expired
					    -- If it is, return FALSE
					    if l_expires_on > sysdate or l_expires_on is null then
					 
					      -- If the account is not expired, we have to apply the custom hash 
					      -- function to the password
					      l_password := custom_hash(p_username, p_password);
					 
					      -- Finally, we compare them to see if they are the same and return 
					      -- either TRUE or FALSE
					      if l_password = l_stored_password then
					        return true;
					      else
					        return false;
					      end if;
					    else
					      return false;   
					    end if;
					  else
					    -- The username provided is not in the DEMO_USERS table
					    return false;
					  end if;
					  end;
/

-- ==== PROCEDURE BRPROC_AREFHAPUSKMR ====
CREATE OR REPLACE PROCEDURE "SIKLIK"."BRPROC_AREFHAPUSKMR" (HRpk in varchar2, kodekelas in varchar2, koderuang in varchar2)
as
--var base url
 vbase_consid varchar2(100);
 vbase_secretkey varchar2(100);
 vbase_url varchar2(4000);
 vbase_url_jadi varchar2(4000);
 vbase_url_local varchar2(4000);
 vbasepkk varchar2(100);
--var utl http
 req utl_http.req;
 resp utl_http.resp;
 value1 clob;
 Lvalue1 number;
 vresult clob;
 v_url varchar2(4000);
 v_post varchar2(4000);
--var coloumns
 CKkdcode    varchar2(100);
 CKkdmessage    varchar2(200);
BEGIN
--find base url
  select BASE_PPKRS,base_url_applicare,base_url_local,base_consid,base_secretkey into vbasepkk,vbase_url,vbase_url_local,vbase_consid,vbase_secretkey from BRMST_BASEBRIDSEPS;
  vbase_url_jadi:=vbase_url||'aplicaresws/rest/bed/delete/'||vbasepkk;
--post var value
  v_post:='consid='||vbase_consid||'&'||'secretkey='||vbase_secretkey||'&'||'url='||vbase_url_jadi||'&'||'kodekelas='||kodekelas||'&'||'koderuang='||koderuang;
    --v_url:='http://localhost/bridgingbpjs/contohKelas.xml';
    v_url:=vbase_url_local||'Refhapuskamar';
    req := utl_http.begin_request(v_url,'POST');
    UTL_HTTP.SET_HEADER(req,'User-Agent','Mozilla/4.0');
    UTL_HTTP.SET_HEADER (req,'Content-Type','application/x-www-form-urlencoded');
    UTL_HTTP.SET_HEADER (req,'Content-Length',length(v_post));
    UTL_HTTP.WRITE_TEXT (req,v_post);
    resp := utl_http.get_response(req);
       vresult := EMPTY_CLOB;
       --get length clob
        select dbms_lob.getlength(value1) into Lvalue1 from dual;
   LOOP
     UTL_HTTP.READ_TEXT(resp, value1, Lvalue1);
     vresult := vresult || value1;
   END LOOP;
      utl_http.end_response(resp);
    EXCEPTION
      WHEN utl_http.end_of_body THEN
      utl_http.end_response(resp);
--DBMS_OUTPUT.PUT_LINE(vresult);
--extrack xml's value
--extracting multiple node from xml
    select extractvalue(xmltype.createxml(vresult),'/myroot/metadata/code'),
    extractvalue(xmltype.createxml(vresult),'/myroot/metadata/message')
    into
    CKkdcode,
    CKkdmessage
    from dual;
      --insert xml value into table
      insert into BRMST_ARUANGHAPUSES(RH_PK,RH_CODE,RH_MESSAGE)values(HRpk,CKkdcode,CKkdmessage);
    COMMIT;
END brproc_arefHAPUSkmr;
/

-- ==== PROCEDURE BRPROC_AREFKELAS ====
CREATE OR REPLACE PROCEDURE "SIKLIK"."BRPROC_AREFKELAS" 
as
--var base url
 vbase_consid varchar2(100);
 vbase_secretkey varchar2(100);
 vbase_url varchar2(4000);
 vbase_url_jadi varchar2(4000);
 vbase_url_local varchar2(4000);
--var utl http
 req utl_http.req;
 resp utl_http.resp;
 value1 clob;
 Lvalue1 number;
 vresult clob;
 v_url varchar2(4000);
 v_post varchar2(4000);
--var coloumns
 CKkdKelas    varchar2(100);
 CKnmKelas    varchar2(100);
BEGIN
--find base url
  select base_url_applicare,base_url_local,base_consid,base_secretkey into vbase_url,vbase_url_local,vbase_consid,vbase_secretkey from BRMST_BASEBRIDSEPS;
  vbase_url_jadi:=vbase_url||'aplicaresws/rest/ref/kelas';
--post var value
  v_post:='consid='||vbase_consid||'&'||'secretkey='||vbase_secretkey||'&'||'url='||vbase_url_jadi;
    --v_url:='http://localhost/bridgingbpjs/contohKelas.xml';
    v_url:=vbase_url_local||'Refkelas';
    req := utl_http.begin_request(v_url,'POST');
    UTL_HTTP.SET_HEADER(req,'User-Agent','Mozilla/4.0');
    UTL_HTTP.SET_HEADER (req,'Content-Type','application/x-www-form-urlencoded');
    UTL_HTTP.SET_HEADER (req,'Content-Length',length(v_post));
    UTL_HTTP.WRITE_TEXT (req,v_post);
    resp := utl_http.get_response(req);
       vresult := EMPTY_CLOB;
       --get length clob
        select dbms_lob.getlength(value1) into Lvalue1 from dual;
   LOOP
     UTL_HTTP.READ_TEXT(resp, value1, Lvalue1);
     vresult := vresult || value1;
   END LOOP;
      utl_http.end_response(resp);
    EXCEPTION
      WHEN utl_http.end_of_body THEN
      utl_http.end_response(resp);
-- delete log table BRMST_AREFKELASES
delete from BRMST_AREFKELASES;
commit;
--extrack xml's value
--extracting multiple node from xml
    FOR i IN
      (
select kdKelas,nmKelas
FROM XMLTABLE('/myroot/response/list'
         PASSING
            xmltype(vresult)
         COLUMNS
            --describe columns and path to them:
            kdKelas  varchar2(120)    PATH './kodekelas',
            nmKelas varchar2(120)    PATH './namakelas'
     ) xmlt
       )
    LOOP
      --insert xml value into table
      insert into BRMST_AREFKELASES(kodeKelas,namaKelas)values(i.kdKelas,i.nmKelas);
    END LOOP;
    COMMIT;
END brproc_arefkelas;
/

-- ==== PROCEDURE BRPROC_AREFTAMBAHKMR ====
CREATE OR REPLACE PROCEDURE "SIKLIK"."BRPROC_AREFTAMBAHKMR" (arpk in varchar2, kodekelas in varchar2, koderuang in varchar2, namaruang in varchar2)
as
--var base url
 vbase_consid varchar2(100);
 vbase_secretkey varchar2(100);
 vbase_url varchar2(4000);
 vbase_url_jadi varchar2(4000);
 vbase_url_local varchar2(4000);
 vbasepkk varchar2(100);
--var utl http
 req utl_http.req;
 resp utl_http.resp;
 value1 clob;
 Lvalue1 number;
 vresult clob;
 v_url varchar2(4000);
 v_post varchar2(4000);
--var coloumns
 CKkdcode    varchar2(100);
 CKkdmessage    varchar2(200);
BEGIN
--find base url
  select BASE_PPKRS,base_url_applicare,base_url_local,base_consid,base_secretkey into vbasepkk,vbase_url,vbase_url_local,vbase_consid,vbase_secretkey from BRMST_BASEBRIDSEPS;
  vbase_url_jadi:=vbase_url||'aplicaresws/rest/bed/create/'||vbasepkk;
--post var value
  v_post:='consid='||vbase_consid||'&'||'secretkey='||vbase_secretkey||'&'||'url='||vbase_url_jadi||'&'||'kodekelas='||kodekelas||'&'||'koderuang='||koderuang||'&'||'namaruang='||namaruang;
    --v_url:='http://localhost/bridgingbpjs/contohKelas.xml';
    v_url:=vbase_url_local||'Reftambahkamar';
    req := utl_http.begin_request(v_url,'POST');
    UTL_HTTP.SET_HEADER(req,'User-Agent','Mozilla/4.0');
    UTL_HTTP.SET_HEADER (req,'Content-Type','application/x-www-form-urlencoded');
    UTL_HTTP.SET_HEADER (req,'Content-Length',length(v_post));
    UTL_HTTP.WRITE_TEXT (req,v_post);
    resp := utl_http.get_response(req);
       vresult := EMPTY_CLOB;
       --get length clob
        select dbms_lob.getlength(value1) into Lvalue1 from dual;
   LOOP
     UTL_HTTP.READ_TEXT(resp, value1, Lvalue1);
     vresult := vresult || value1;
   END LOOP;
      utl_http.end_response(resp);
    EXCEPTION
      WHEN utl_http.end_of_body THEN
      utl_http.end_response(resp);
--DBMS_OUTPUT.PUT_LINE(vresult);
--extrack xml's value
--extracting multiple node from xml
    select extractvalue(xmltype.createxml(vresult),'/myroot/metadata/code'),
    extractvalue(xmltype.createxml(vresult),'/myroot/metadata/message')
    into
    CKkdcode,
    CKkdmessage
    from dual;
      --insert xml value into table
      insert into BRMST_ARUANGBARUS(AR_PK,AR_CODE,AR_MESSAGE)values(arpk,CKkdcode,CKkdmessage);
    COMMIT;
END brproc_areftambahkmr;
/

-- ==== PROCEDURE BRPROC_AREFUPDATEKMR ====
CREATE OR REPLACE PROCEDURE "SIKLIK"."BRPROC_AREFUPDATEKMR" (RUpk in varchar2, kodekelas in varchar2, koderuang in varchar2, namaruang in varchar2, kapasitas in varchar2, tersedia in varchar2, tersediapria in varchar2, tersediawanita in varchar2, tersediapriawanita in varchar2)
    as
    --var base url
     vbase_consid varchar2(100);
     vbase_secretkey varchar2(100);
     vbase_url varchar2(4000);
     vbase_url_jadi varchar2(4000);
     vbase_url_local varchar2(4000);
     vbasepkk varchar2(100);
   --var utl http
    req utl_http.req;
    resp utl_http.resp;
    value1 clob;
    Lvalue1 number;
    vresult clob;
    v_url varchar2(4000);
    v_post varchar2(4000);
   --var coloumns
    CKkdcode    varchar2(100);
    CKkdmessage    varchar2(200);
   BEGIN
   --find base url
     select BASE_PPKRS,base_url_applicare,base_url_local,base_consid,base_secretkey into vbasepkk,vbase_url,vbase_url_local,vbase_consid,vbase_secretkey from BRMST_BASEBRIDSEPS;
     vbase_url_jadi:=vbase_url||'aplicaresws/rest/bed/update/'||vbasepkk;
   --post var value
     v_post:='consid='||vbase_consid||'&'||'secretkey='||vbase_secretkey||'&'||'url='||vbase_url_jadi||'&'||'kodekelas='||kodekelas||'&'||'koderuang='||koderuang||'&'||'namaruang='||namaruang||'&'||'kapasitas='||kapasitas||'&'||'tersedia='||tersedia||'&'||'tersediapria='||tersediapria||'&'||'tersediawanita='||tersediawanita||'&'||'tersediapriawanita='||tersediapriawanita;
       --v_url:='http://localhost/bridgingbpjs/contohKelas.xml';
       v_url:=vbase_url_local||'Refupdatekamar';
       req := utl_http.begin_request(v_url,'POST');
       UTL_HTTP.SET_HEADER(req,'User-Agent','Mozilla/4.0');
       UTL_HTTP.SET_HEADER (req,'Content-Type','application/x-www-form-urlencoded');
       UTL_HTTP.SET_HEADER (req,'Content-Length',length(v_post));
       UTL_HTTP.WRITE_TEXT (req,v_post);
       resp := utl_http.get_response(req);
          vresult := EMPTY_CLOB;
          --get length clob
           select dbms_lob.getlength(value1) into Lvalue1 from dual;
      LOOP
        UTL_HTTP.READ_TEXT(resp, value1, Lvalue1);
        vresult := vresult || value1;
      END LOOP;
         utl_http.end_response(resp);
       EXCEPTION
         WHEN utl_http.end_of_body THEN
         utl_http.end_response(resp);
   --DBMS_OUTPUT.PUT_LINE(vresult);
   --extrack xml's value
   --extracting multiple node from xml
       select extractvalue(xmltype.createxml(vresult),'/myroot/metadata/code'),
       extractvalue(xmltype.createxml(vresult),'/myroot/metadata/message')
       into
       CKkdcode,
       CKkdmessage
       from dual;
         --insert xml value into table
         insert into BRMST_ARUANGUPADATES(RU_PK,RU_CODE,RU_MESSAGE)values(RUpk,CKkdcode,CKkdmessage);
       COMMIT;
   END brproc_arefupdatekmr;
/

-- ==== PROCEDURE BRPROC_CARI_KTSP ====
CREATE OR REPLACE PROCEDURE "SIKLIK"."BRPROC_CARI_KTSP" (vnokartu in varchar2,vckpk in varchar2,vtglsep in varchar2)
as
--var base url
	vbase_consid varchar2(100);
	vbase_secretkey varchar2(100);
	vbase_url varchar2(4000);
	vbase_url_jadi varchar2(4000);
	vbase_url_local varchar2(4000);
	vbase_nokartu varchar2(100);
--var utl http
	req utl_http.req;
	resp utl_http.resp;
    value1 clob;
    Lvalue1 number;
	vresult clob;
	v_url varchar2(4000);
	v_post varchar2(4000);
--var coloumns
	CKcode    varchar2(100);
	CKmessage    varchar2(1000);
	CKdinsos    varchar2(100);
	CKiuran    varchar2(100);
	CKnoSKTM    varchar2(100);
	CKprolanisPRB    varchar2(100);
	CKkdJenisPeserta    varchar2(100);
	CKnmJenisPeserta    varchar2(100);
	CKkdKelas    varchar2(100);
	CKnmKelas    varchar2(100);
	CKnama    varchar2(100);
	CKnik    varchar2(100);
	CKnoKartu    varchar2(100);
	CKnoMr    varchar2(100);
	CKpisa    varchar2(100);
	CKkdCabang    varchar2(100);
	CKkdProvider    varchar2(100);
	CKnmCabang    varchar2(100);
	CKnmProvider    varchar2(100);
	CKsex    varchar2(100);
	CKketerangan    varchar2(100);
	CKkode    varchar2(100);
	CKtglCetakKartu    varchar2(100);
	CKtglLahir    varchar2(100);
	CKtglTAT    varchar2(100);
	CKtglTMT    varchar2(100);
	CKumurSaatPelayanan    varchar2(100);
	CKumurSekarang    varchar2(100);
    CKnoTelepon    varchar2(100);
BEGIN
--find base url
		select base_url,base_url_local,base_consid,base_secretkey into vbase_url,vbase_url_local,vbase_consid,vbase_secretkey from BRMST_BASEBRIDSEPS;
		vbase_url_jadi:=vbase_url||'Peserta/nokartu/'||vnokartu||'/tglSEP/'||vtglsep;
--post var value
		     v_post:='consid='||vbase_consid||'&'||'secretkey='||vbase_secretkey||'&'||'url='||vbase_url_jadi;
			 v_url:=vbase_url_local||'GetByNoKartu';
             --v_url:='http://localhost/bridgingbpjs/contohpeserta.xml';
			 req := utl_http.begin_request(v_url,'POST');
			 UTL_HTTP.SET_HEADER(req,'User-Agent','Mozilla/4.0');
			 UTL_HTTP.SET_HEADER (req,'Content-Type','application/x-www-form-urlencoded');
			 UTL_HTTP.SET_HEADER (req,'Content-Length',length(v_post));
			 UTL_HTTP.WRITE_TEXT (req,v_post);
			 resp := utl_http.get_response(req);
			    vresult := EMPTY_CLOB;
       --get length clob
        select dbms_lob.getlength(value1) into Lvalue1 from dual;
			LOOP
			  UTL_HTTP.READ_TEXT(resp, value1, Lvalue1);
			  vresult := vresult || value1;
			END LOOP;
			   utl_http.end_response(resp);
			 EXCEPTION
			   WHEN utl_http.end_of_body THEN
			   utl_http.end_response(resp);
--extrack xml's value
--extracting multiple node from xml
    FOR i IN
      (
select CK_CODE
,CK_MESSAGE
,CK_NMKELAS
,CK_KDKELAS
,CK_DINSOS
,CK_NOSKTM
,CK_PROLANISPRB
,CK_NMJENISPESERTA
,CK_KDJENISPESERTA
,CK_NOMR
,CK_NOTELEPON
,CK_NAMA
,CK_NIK
,CK_NOKARTU
,CK_PISA
,CK_KDPROVIDER
,CK_NMPROVIDER
,CK_SEX
,CK_KETERANGAN
,CK_KODE
,CK_TGLCETAKKARTU
,CK_TGLLAHIR
,CK_TGLTAT
,CK_TGLTMT
,CK_UMURSAATPELAYANAN
,CK_UMURSEKARANG
FROM XMLTABLE('/myroot'
         PASSING
            xmltype(vresult)
         COLUMNS
            --describe columns and path to them:
             --describe columns and path to them:
            CK_CODE varchar2(200) PATH'./metaData/code',
            CK_MESSAGE varchar2(200) PATH'./metaData/message',
            CK_NMKELAS varchar2(200) PATH'./response/peserta/hakKelas/keterangan',
            CK_KDKELAS varchar2(200) PATH'./response/peserta/hakKelas/kode',
            CK_DINSOS varchar2(200) PATH'./response/peserta/informasi/dinsos',
            CK_NOSKTM varchar2(200) PATH'./response/peserta/informasi/noSKTM',
            CK_PROLANISPRB varchar2(200) PATH'./response/peserta/informasi/prolanisPRB',
            CK_NMJENISPESERTA varchar2(200) PATH'./response/peserta/jenisPeserta/keterangan',
            CK_KDJENISPESERTA varchar2(200) PATH'./response/peserta/jenisPeserta/kode',
            CK_NOMR varchar2(200) PATH'./response/peserta/mr/noMR',
            CK_NOTELEPON varchar2(200) PATH'./response/peserta/mr/noTelepon',
            CK_NAMA varchar2(200) PATH'./response/peserta/nama',
            CK_NIK varchar2(200) PATH'./response/peserta/nik',
            CK_NOKARTU varchar2(200) PATH'./response/peserta/noKartu',
            CK_PISA varchar2(200) PATH'./response/peserta/pisa',
            CK_KDPROVIDER varchar2(200) PATH'./response/peserta/provUmum/kdProvider',
            CK_NMPROVIDER varchar2(200) PATH'./response/peserta/provUmum/nmProvider',
            CK_SEX varchar2(200) PATH'./response/peserta/sex',
            CK_KETERANGAN varchar2(200) PATH'./response/peserta/statusPeserta/keterangan',
            CK_KODE varchar2(200) PATH'./response/peserta/statusPeserta/kode',
            CK_TGLCETAKKARTU varchar2(200) PATH'./response/peserta/tglCetakKartu',
            CK_TGLLAHIR varchar2(200) PATH'./response/peserta/tglLahir',
            CK_TGLTAT varchar2(200) PATH'./response/peserta/tglTAT',
            CK_TGLTMT varchar2(200) PATH'./response/peserta/tglTMT',
            CK_UMURSAATPELAYANAN varchar2(200) PATH'./response/peserta/umur/umurSaatPelayanan',
            CK_UMURSEKARANG varchar2(200) PATH'./response/peserta/umur/umurSekarang'
     ) xmlt       )
    LOOP
--insert xml value into table
			insert into BRMST_CARI_KTSPS
			(CK_pk,
			CK_code,
			CK_message,
			CK_dinsos,
			CK_iuran,
			CK_noSKTM,
			CK_prolanisPRB,
			CK_kdJenisPeserta,
			CK_nmJenisPeserta,
			CK_kdKelas,
			CK_nmKelas,
			CK_nama,
			CK_nik,
			CK_noKartu,
			CK_noMr,
			CK_pisa,
			CK_kdCabang,
			CK_kdProvider,
			CK_nmCabang,
			CK_nmProvider,
			CK_sex,
			CK_keterangan,
			CK_kode,
			CK_tglCetakKartu,
			CK_tglLahir,
			CK_tglTAT,
			CK_tglTMT,
			CK_umurSaatPelayanan,
			CK_umurSekarang,
			CK_noTelepon)
			values
			(vckpk,
			i.CK_code,
			i.CK_message,
			i.CK_dinsos,
			null,
			i.CK_noSKTM,
			i.CK_prolanisPRB,
			i.CK_kdJenisPeserta,
			i.CK_nmJenisPeserta,
			i.CK_kdKelas,
			i.CK_nmKelas,
			i.CK_nama,
			i.CK_nik,
			i.CK_noKartu,
			i.CK_noMr,
			i.CK_pisa,
			null,
			i.CK_kdProvider,
			null,
			i.CK_nmProvider,
			i.CK_sex,
			i.CK_keterangan,
			i.CK_kode,
			i.CK_tglCetakKartu,
			i.CK_tglLahir,
			i.CK_tglTAT,
			i.CK_tglTMT,
			i.CK_umurSaatPelayanan,
			i.CK_umurSekarang,
			i.CK_noTelepon);
    END LOOP;
    COMMIT;
END brproc_cari_ktsp;
/

-- ==== PROCEDURE BRPROC_CARI_NIK ====
CREATE OR REPLACE PROCEDURE "SIKLIK"."BRPROC_CARI_NIK" (vnokartu in varchar2,vckpk in varchar2,vtglsep in varchar2)
as
--var base url
	vbase_consid varchar2(100);
	vbase_secretkey varchar2(100);
	vbase_url varchar2(4000);
	vbase_url_jadi varchar2(4000);
	vbase_url_local varchar2(4000);
	vbase_nokartu varchar2(100);
--var utl http
	req utl_http.req;
	resp utl_http.resp;
    value1 clob;
    Lvalue1 number;
	vresult clob;
	v_url varchar2(4000);
	v_post varchar2(4000);
--var coloumns
	CKcode    varchar2(100);
	CKmessage    varchar2(1000);
	CKdinsos    varchar2(100);
	CKiuran    varchar2(100);
	CKnoSKTM    varchar2(100);
	CKprolanisPRB    varchar2(100);
	CKkdJenisPeserta    varchar2(100);
	CKnmJenisPeserta    varchar2(100);
	CKkdKelas    varchar2(100);
	CKnmKelas    varchar2(100);
	CKnama    varchar2(100);
	CKnik    varchar2(100);
	CKnoKartu    varchar2(100);
	CKnoMr    varchar2(100);
	CKpisa    varchar2(100);
	CKkdCabang    varchar2(100);
	CKkdProvider    varchar2(100);
	CKnmCabang    varchar2(100);
	CKnmProvider    varchar2(100);
	CKsex    varchar2(100);
	CKketerangan    varchar2(100);
	CKkode    varchar2(100);
	CKtglCetakKartu    varchar2(100);
	CKtglLahir    varchar2(100);
	CKtglTAT    varchar2(100);
	CKtglTMT    varchar2(100);
	CKumurSaatPelayanan    varchar2(100);
	CKumurSekarang    varchar2(100);
    CKnoTelepon    varchar2(100);
BEGIN
--find base url
		select base_url,base_url_local,base_consid,base_secretkey into vbase_url,vbase_url_local,vbase_consid,vbase_secretkey from BRMST_BASEBRIDSEPS;
		vbase_url_jadi:=vbase_url||'Peserta/nik/'||vnokartu||'/tglSEP/'||vtglsep;
--post var value
		     v_post:='consid='||vbase_consid||'&'||'secretkey='||vbase_secretkey||'&'||'url='||vbase_url_jadi;
			 v_url:=vbase_url_local||'GetByNoKartu';
             --v_url:='http://localhost/bridgingbpjs/contohpeserta.xml';
			 req := utl_http.begin_request(v_url,'POST');
			 UTL_HTTP.SET_HEADER(req,'User-Agent','Mozilla/4.0');
			 UTL_HTTP.SET_HEADER (req,'Content-Type','application/x-www-form-urlencoded');
			 UTL_HTTP.SET_HEADER (req,'Content-Length',length(v_post));
			 UTL_HTTP.WRITE_TEXT (req,v_post);
			 resp := utl_http.get_response(req);
			    vresult := EMPTY_CLOB;
       --get length clob
        select dbms_lob.getlength(value1) into Lvalue1 from dual;
			LOOP
			  UTL_HTTP.READ_TEXT(resp, value1, Lvalue1);
			  vresult := vresult || value1;
			END LOOP;
			   utl_http.end_response(resp);
			 EXCEPTION
			   WHEN utl_http.end_of_body THEN
			   utl_http.end_response(resp);
--extrack xml's value
--extracting multiple node from xml
    FOR i IN
      (
select CK_CODE
,CK_MESSAGE
,CK_NMKELAS
,CK_KDKELAS
,CK_DINSOS
,CK_NOSKTM
,CK_PROLANISPRB
,CK_NMJENISPESERTA
,CK_KDJENISPESERTA
,CK_NOMR
,CK_NOTELEPON
,CK_NAMA
,CK_NIK
,CK_NOKARTU
,CK_PISA
,CK_KDPROVIDER
,CK_NMPROVIDER
,CK_SEX
,CK_KETERANGAN
,CK_KODE
,CK_TGLCETAKKARTU
,CK_TGLLAHIR
,CK_TGLTAT
,CK_TGLTMT
,CK_UMURSAATPELAYANAN
,CK_UMURSEKARANG
FROM XMLTABLE('/myroot'
         PASSING
            xmltype(vresult)
         COLUMNS
            --describe columns and path to them:
             --describe columns and path to them:
            CK_CODE varchar2(200) PATH'./metaData/code',
            CK_MESSAGE varchar2(200) PATH'./metaData/message',
            CK_NMKELAS varchar2(200) PATH'./response/peserta/hakKelas/keterangan',
            CK_KDKELAS varchar2(200) PATH'./response/peserta/hakKelas/kode',
            CK_DINSOS varchar2(200) PATH'./response/peserta/informasi/dinsos',
            CK_NOSKTM varchar2(200) PATH'./response/peserta/informasi/noSKTM',
            CK_PROLANISPRB varchar2(200) PATH'./response/peserta/informasi/prolanisPRB',
            CK_NMJENISPESERTA varchar2(200) PATH'./response/peserta/jenisPeserta/keterangan',
            CK_KDJENISPESERTA varchar2(200) PATH'./response/peserta/jenisPeserta/kode',
            CK_NOMR varchar2(200) PATH'./response/peserta/mr/noMR',
            CK_NOTELEPON varchar2(200) PATH'./response/peserta/mr/noTelepon',
            CK_NAMA varchar2(200) PATH'./response/peserta/nama',
            CK_NIK varchar2(200) PATH'./response/peserta/nik',
            CK_NOKARTU varchar2(200) PATH'./response/peserta/noKartu',
            CK_PISA varchar2(200) PATH'./response/peserta/pisa',
            CK_KDPROVIDER varchar2(200) PATH'./response/peserta/provUmum/kdProvider',
            CK_NMPROVIDER varchar2(200) PATH'./response/peserta/provUmum/nmProvider',
            CK_SEX varchar2(200) PATH'./response/peserta/sex',
            CK_KETERANGAN varchar2(200) PATH'./response/peserta/statusPeserta/keterangan',
            CK_KODE varchar2(200) PATH'./response/peserta/statusPeserta/kode',
            CK_TGLCETAKKARTU varchar2(200) PATH'./response/peserta/tglCetakKartu',
            CK_TGLLAHIR varchar2(200) PATH'./response/peserta/tglLahir',
            CK_TGLTAT varchar2(200) PATH'./response/peserta/tglTAT',
            CK_TGLTMT varchar2(200) PATH'./response/peserta/tglTMT',
            CK_UMURSAATPELAYANAN varchar2(200) PATH'./response/peserta/umur/umurSaatPelayanan',
            CK_UMURSEKARANG varchar2(200) PATH'./response/peserta/umur/umurSekarang'
     ) xmlt       )
    LOOP
--insert xml value into table
			insert into BRMST_CARI_KTSPS
			(CK_pk,
			CK_code,
			CK_message,
			CK_dinsos,
			CK_iuran,
			CK_noSKTM,
			CK_prolanisPRB,
			CK_kdJenisPeserta,
			CK_nmJenisPeserta,
			CK_kdKelas,
			CK_nmKelas,
			CK_nama,
			CK_nik,
			CK_noKartu,
			CK_noMr,
			CK_pisa,
			CK_kdCabang,
			CK_kdProvider,
			CK_nmCabang,
			CK_nmProvider,
			CK_sex,
			CK_keterangan,
			CK_kode,
			CK_tglCetakKartu,
			CK_tglLahir,
			CK_tglTAT,
			CK_tglTMT,
			CK_umurSaatPelayanan,
			CK_umurSekarang,
			CK_noTelepon)
			values
			(vckpk,
			i.CK_code,
			i.CK_message,
			i.CK_dinsos,
			null,
			i.CK_noSKTM,
			i.CK_prolanisPRB,
			i.CK_kdJenisPeserta,
			i.CK_nmJenisPeserta,
			i.CK_kdKelas,
			i.CK_nmKelas,
			i.CK_nama,
			i.CK_nik,
			i.CK_noKartu,
			i.CK_noMr,
			i.CK_pisa,
			null,
			i.CK_kdProvider,
			null,
			i.CK_nmProvider,
			i.CK_sex,
			i.CK_keterangan,
			i.CK_kode,
			i.CK_tglCetakKartu,
			i.CK_tglLahir,
			i.CK_tglTAT,
			i.CK_tglTMT,
			i.CK_umurSaatPelayanan,
			i.CK_umurSekarang,
			i.CK_noTelepon);
    END LOOP;
    COMMIT;
END brproc_cari_NIK;
/

-- ==== PROCEDURE BRPROC_CARI_NORUJUKAN ====
CREATE OR REPLACE PROCEDURE "SIKLIK"."BRPROC_CARI_NORUJUKAN" (vnorujukan in varchar2,vckpk in varchar2)
as
--var base url
	vbase_consid varchar2(100);
	vbase_secretkey varchar2(100);
	vbase_url varchar2(4000);
	vbase_url_jadi varchar2(4000);
	vbase_url_local varchar2(4000);
	vbase_nokartu varchar2(100);
--var utl http
	req utl_http.req;
	resp utl_http.resp;
    value1 clob;
    Lvalue1 number;
	vresult clob;
	v_url varchar2(4000);
	v_post varchar2(4000);
--var coloumns
	CKcode    varchar2(100);
	CKmessage    varchar2(1000);
	CKdinsos    varchar2(100);
	CKiuran    varchar2(100);
	CKnoSKTM    varchar2(100);
	CKprolanisPRB    varchar2(100);
	CKkdJenisPeserta    varchar2(100);
	CKnmJenisPeserta    varchar2(100);
	CKkdKelas    varchar2(100);
	CKnmKelas    varchar2(100);
	CKnama    varchar2(100);
	CKnik    varchar2(100);
	CKnoKartu    varchar2(100);
	CKnoMr    varchar2(100);
	CKpisa    varchar2(100);
	CKkdCabang    varchar2(100);
	CKkdProvider    varchar2(100);
	CKnmCabang    varchar2(100);
	CKnmProvider    varchar2(100);
	CKsex    varchar2(100);
	CKketerangan    varchar2(100);
	CKkode    varchar2(100);
	CKtglCetakKartu    varchar2(100);
	CKtglLahir    varchar2(100);
	CKtglTAT    varchar2(100);
	CKtglTMT    varchar2(100);
	CKumurSaatPelayanan    varchar2(100);
	CKumurSekarang    varchar2(100);
    CKnoTelepon    varchar2(100);
CRDIAGNOSA_AWAL varchar2(100);
CRDIAGNOSA_AWAL_DESC varchar2(100);
CRnoKunjungan varchar2(100);
CRpelayanan varchar2(100);
CRpelayanan_desc varchar2(100);
CRpoliRujukan varchar2(100);
CRpoliRujukan_desc varchar2(100);
CRprovPerujuk varchar2(100);
CRprovPerujuk_desc varchar2(100);
CRtglKunjungan varchar2(100);
BEGIN
--find base url
		select base_url,base_url_local,base_consid,base_secretkey into vbase_url,vbase_url_local,vbase_consid,vbase_secretkey from BRMST_BASEBRIDSEPS;
		vbase_url_jadi:=vbase_url||'Rujukan/'||vnorujukan;
--post var value
		     v_post:='consid='||vbase_consid||'&'||'secretkey='||vbase_secretkey||'&'||'url='||vbase_url_jadi;
			 v_url:=vbase_url_local||'GetNoRujukan';
             --v_url:='http://localhost/bridgingbpjs/contohpeserta.xml';
			 req := utl_http.begin_request(v_url,'POST');
			 UTL_HTTP.SET_HEADER(req,'User-Agent','Mozilla/4.0');
			 UTL_HTTP.SET_HEADER (req,'Content-Type','application/x-www-form-urlencoded');
			 UTL_HTTP.SET_HEADER (req,'Content-Length',length(v_post));
			 UTL_HTTP.WRITE_TEXT (req,v_post);
			 resp := utl_http.get_response(req);
			    vresult := EMPTY_CLOB;
       --get length clob
        select dbms_lob.getlength(value1) into Lvalue1 from dual;
			LOOP
			  UTL_HTTP.READ_TEXT(resp, value1, Lvalue1);
			  vresult := vresult || value1;
			END LOOP;
			   utl_http.end_response(resp);
			 EXCEPTION
			   WHEN utl_http.end_of_body THEN
			   utl_http.end_response(resp);
--extrack xml's value
--extracting multiple node from xml
    FOR i IN
      (
select CK_CODE
,CK_MESSAGE
,CK_NMKELAS
,CK_KDKELAS
,CK_DINSOS
,CK_NOSKTM
,CK_PROLANISPRB
,CK_NMJENISPESERTA
,CK_KDJENISPESERTA
,CK_NOMR
,CK_NOTELEPON
,CK_NAMA
,CK_NIK
,CK_NOKARTU
,CK_PISA
,CK_KDPROVIDER
,CK_NMPROVIDER
,CK_SEX
,CK_KETERANGAN
,CK_KODE
,CK_TGLCETAKKARTU
,CK_TGLLAHIR
,CK_TGLTAT
,CK_TGLTMT
,CK_UMURSAATPELAYANAN
,CK_UMURSEKARANG,
CR_DIAGNOSA_AWAL,
CR_DIAGNOSA_AWAL_DESC,
CR_noKunjungan,
CR_pelayanan,
CR_pelayanan_desc,
CR_poliRujukan,
CR_poliRujukan_desc,
CR_provPerujuk,
CR_provPerujuk_desc,
CR_tglKunjungan
FROM XMLTABLE('/myroot'
         PASSING
            xmltype(vresult)
         COLUMNS
            --describe columns and path to them:
             --describe columns and path to them:
            CK_CODE varchar2(200) PATH'./metaData/code',
            CK_MESSAGE varchar2(200) PATH'./metaData/message',
            CK_NMKELAS varchar2(200) PATH'./response/rujukan/peserta/hakKelas/keterangan',
            CK_KDKELAS varchar2(200) PATH'./response/rujukan/peserta/hakKelas/kode',
            CK_DINSOS varchar2(200) PATH'./response/rujukan/peserta/informasi/dinsos',
            CK_NOSKTM varchar2(200) PATH'./response/rujukan/peserta/informasi/noSKTM',
            CK_PROLANISPRB varchar2(200) PATH'./response/rujukan/peserta/informasi/prolanisPRB',
            CK_NMJENISPESERTA varchar2(200) PATH'./response/rujukan/peserta/jenisPeserta/keterangan',
            CK_KDJENISPESERTA varchar2(200) PATH'./response/rujukan/peserta/jenisPeserta/kode',
            CK_NOMR varchar2(200) PATH'./response/rujukan/peserta/mr/noMR',
            CK_NOTELEPON varchar2(200) PATH'./response/rujukan/peserta/mr/noTelepon',
            CK_NAMA varchar2(200) PATH'./response/rujukan/peserta/nama',
            CK_NIK varchar2(200) PATH'./response/rujukan/peserta/nik',
            CK_NOKARTU varchar2(200) PATH'./response/rujukan/peserta/noKartu',
            CK_PISA varchar2(200) PATH'./response/rujukan/peserta/pisa',
            CK_KDPROVIDER varchar2(200) PATH'./response/rujukan/peserta/provUmum/kdProvider',
            CK_NMPROVIDER varchar2(200) PATH'./response/rujukan/peserta/provUmum/nmProvider',
            CK_SEX varchar2(200) PATH'./response/rujukan/peserta/sex',
            CK_KETERANGAN varchar2(200) PATH'./response/rujukan/peserta/statusPeserta/keterangan',
            CK_KODE varchar2(200) PATH'./response/rujukan/peserta/statusPeserta/kode',
            CK_TGLCETAKKARTU varchar2(200) PATH'./response/rujukan/peserta/tglCetakKartu',
            CK_TGLLAHIR varchar2(200) PATH'./response/rujukan/peserta/tglLahir',
            CK_TGLTAT varchar2(200) PATH'./response/rujukan/peserta/tglTAT',
            CK_TGLTMT varchar2(200) PATH'./response/rujukan/peserta/tglTMT',
            CK_UMURSAATPELAYANAN varchar2(200) PATH'./response/rujukan/peserta/umur/umurSaatPelayanan',
            CK_UMURSEKARANG varchar2(200) PATH'./response/rujukan/peserta/umur/umurSekarang',
CR_DIAGNOSA_AWAL varchar2(200) PATH'./response/rujukan/diagnosa/kode',
CR_DIAGNOSA_AWAL_DESC varchar2(200) PATH'./response/rujukan/diagnosa/nama',
CR_noKunjungan varchar2(200) PATH'./response/rujukan/noKunjungan',
CR_pelayanan  varchar2(200) PATH'./response/rujukan/pelayanan/kode',
CR_pelayanan_desc varchar2(200) PATH'./response/rujukan/pelayanan/nama',
CR_poliRujukan varchar2(200) PATH'./response/rujukan/poliRujukan/kode',
CR_poliRujukan_desc varchar2(200) PATH'./response/rujukan/poliRujukan/nama',
CR_provPerujuk varchar2(200) PATH'./response/rujukan/provPerujuk/kode',
CR_provPerujuk_desc varchar2(200) PATH'./response/rujukan/provPerujuk/nama',
CR_tglKunjungan varchar2(200) PATH'./response/rujukan/tglKunjungan'
     ) xmlt       )
    LOOP
--insert xml value into table
-- insert cari rujukan
		insert into BRMST_CARI_RUJUKANS(CR_pk,CR_code,CR_message,DIAGNOSA_AWAL,DIAGNOSA_AWAL_DESC,no_rujukan,JENIS_PELAYANAN,POLI_TUJUAN,POLI_TUJUAN_DESC,TGL_RUJUKAN)
		values(vckpk,i.CK_code,i.CK_message,i.CR_DIAGNOSA_AWAL,i.CR_DIAGNOSA_AWAL_DESC,i.CR_noKunjungan,i.CR_pelayanan,i.CR_poliRujukan,i.CR_poliRujukan_desc,i.CR_tglKunjungan);
 COMMIT;
--insert cari ktsp
			insert into BRMST_CARI_KTSPS
			(CK_pk,
			CK_code,
			CK_message,
			CK_dinsos,
			CK_iuran,
			CK_noSKTM,
			CK_prolanisPRB,
			CK_kdJenisPeserta,
			CK_nmJenisPeserta,
			CK_kdKelas,
			CK_nmKelas,
			CK_nama,
			CK_nik,
			CK_noKartu,
			CK_noMr,
			CK_pisa,
			CK_kdCabang,
			CK_kdProvider,
			CK_nmCabang,
			CK_nmProvider,
			CK_sex,
			CK_keterangan,
			CK_kode,
			CK_tglCetakKartu,
			CK_tglLahir,
			CK_tglTAT,
			CK_tglTMT,
			CK_umurSaatPelayanan,
			CK_umurSekarang,
			CK_noTelepon)
			values
			(vckpk,
			i.CK_code,
			i.CK_message,
			i.CK_dinsos,
			null,
			i.CK_noSKTM,
			i.CK_prolanisPRB,
			i.CK_kdJenisPeserta,
			i.CK_nmJenisPeserta,
			i.CK_kdKelas,
			i.CK_nmKelas,
			i.CK_nama,
			i.CK_nik,
			i.CK_noKartu,
			i.CK_noMr,
			i.CK_pisa,
			null,
			i.CK_kdProvider,
			null,
			i.CK_nmProvider,
			i.CK_sex,
			i.CK_keterangan,
			i.CK_kode,
			i.CK_tglCetakKartu,
			i.CK_tglLahir,
			i.CK_tglTAT,
			i.CK_tglTMT,
			i.CK_umurSaatPelayanan,
			i.CK_umurSekarang,
			i.CK_noTelepon);
    END LOOP;
    COMMIT;
END brproc_cari_norujukan;
/

-- ==== PROCEDURE BRPROC_CARI_NORUJUKANNOKA ====
CREATE OR REPLACE PROCEDURE "SIKLIK"."BRPROC_CARI_NORUJUKANNOKA" (vnorujukanNOKA in varchar2,vckpk in varchar2)
as
--var base url
	vbase_consid varchar2(100);
	vbase_secretkey varchar2(100);
	vbase_url varchar2(4000);
	vbase_url_jadi varchar2(4000);
	vbase_url_local varchar2(4000);
	vbase_nokartu varchar2(100);
--var utl http
	req utl_http.req;
	resp utl_http.resp;
    value1 clob;
    Lvalue1 number;
	vresult clob;
	v_url varchar2(4000);
	v_post varchar2(4000);
--var coloumns
	CKcode    varchar2(100);
	CKmessage    varchar2(1000);
	CKdinsos    varchar2(100);
	CKiuran    varchar2(100);
	CKnoSKTM    varchar2(100);
	CKprolanisPRB    varchar2(100);
	CKkdJenisPeserta    varchar2(100);
	CKnmJenisPeserta    varchar2(100);
	CKkdKelas    varchar2(100);
	CKnmKelas    varchar2(100);
	CKnama    varchar2(100);
	CKnik    varchar2(100);
	CKnoKartu    varchar2(100);
	CKnoMr    varchar2(100);
	CKpisa    varchar2(100);
	CKkdCabang    varchar2(100);
	CKkdProvider    varchar2(100);
	CKnmCabang    varchar2(100);
	CKnmProvider    varchar2(100);
	CKsex    varchar2(100);
	CKketerangan    varchar2(100);
	CKkode    varchar2(100);
	CKtglCetakKartu    varchar2(100);
	CKtglLahir    varchar2(100);
	CKtglTAT    varchar2(100);
	CKtglTMT    varchar2(100);
	CKumurSaatPelayanan    varchar2(100);
	CKumurSekarang    varchar2(100);
    CKnoTelepon    varchar2(100);
CRDIAGNOSA_AWAL varchar2(100);
CRDIAGNOSA_AWAL_DESC varchar2(100);
CRnoKunjungan varchar2(100);
CRpelayanan varchar2(100);
CRpelayanan_desc varchar2(100);
CRpoliRujukan varchar2(100);
CRpoliRujukan_desc varchar2(100);
CRprovPerujuk varchar2(100);
CRprovPerujuk_desc varchar2(100);
CRtglKunjungan varchar2(100);
BEGIN
--find base url
		select base_url,base_url_local,base_consid,base_secretkey into vbase_url,vbase_url_local,vbase_consid,vbase_secretkey from BRMST_BASEBRIDSEPS;
		vbase_url_jadi:=vbase_url||'/Rujukan/Peserta/'||vnorujukanNOKA;
--post var value
		     v_post:='consid='||vbase_consid||'&'||'secretkey='||vbase_secretkey||'&'||'url='||vbase_url_jadi;
			 v_url:=vbase_url_local||'GetNoRujukannoka';
             --v_url:='http://localhost/bridgingbpjs/contohpeserta.xml';
			 req := utl_http.begin_request(v_url,'POST');
			 UTL_HTTP.SET_HEADER(req,'User-Agent','Mozilla/4.0');
			 UTL_HTTP.SET_HEADER (req,'Content-Type','application/x-www-form-urlencoded');
			 UTL_HTTP.SET_HEADER (req,'Content-Length',length(v_post));
			 UTL_HTTP.WRITE_TEXT (req,v_post);
			 resp := utl_http.get_response(req);
			    vresult := EMPTY_CLOB;
       --get length clob
        select dbms_lob.getlength(value1) into Lvalue1 from dual;
			LOOP
			  UTL_HTTP.READ_TEXT(resp, value1, Lvalue1);
			  vresult := vresult || value1;
			END LOOP;
			   utl_http.end_response(resp);
			 EXCEPTION
			   WHEN utl_http.end_of_body THEN
			   utl_http.end_response(resp);
--extrack xml's value
--extracting multiple node from xml
    FOR i IN
      (
select CK_CODE
,CK_MESSAGE
,CK_NMKELAS
,CK_KDKELAS
,CK_DINSOS
,CK_NOSKTM
,CK_PROLANISPRB
,CK_NMJENISPESERTA
,CK_KDJENISPESERTA
,CK_NOMR
,CK_NOTELEPON
,CK_NAMA
,CK_NIK
,CK_NOKARTU
,CK_PISA
,CK_KDPROVIDER
,CK_NMPROVIDER
,CK_SEX
,CK_KETERANGAN
,CK_KODE
,CK_TGLCETAKKARTU
,CK_TGLLAHIR
,CK_TGLTAT
,CK_TGLTMT
,CK_UMURSAATPELAYANAN
,CK_UMURSEKARANG,
CR_DIAGNOSA_AWAL,
CR_DIAGNOSA_AWAL_DESC,
CR_noKunjungan,
CR_pelayanan,
CR_pelayanan_desc,
CR_poliRujukan,
CR_poliRujukan_desc,
CR_provPerujuk,
CR_provPerujuk_desc,
CR_tglKunjungan
FROM XMLTABLE('/myroot'
         PASSING
            xmltype(vresult)
         COLUMNS
            --describe columns and path to them:
             --describe columns and path to them:
            CK_CODE varchar2(200) PATH'./metaData/code',
            CK_MESSAGE varchar2(200) PATH'./metaData/message',
            CK_NMKELAS varchar2(200) PATH'./response/rujukan/peserta/hakKelas/keterangan',
            CK_KDKELAS varchar2(200) PATH'./response/rujukan/peserta/hakKelas/kode',
            CK_DINSOS varchar2(200) PATH'./response/rujukan/peserta/informasi/dinsos',
            CK_NOSKTM varchar2(200) PATH'./response/rujukan/peserta/informasi/noSKTM',
            CK_PROLANISPRB varchar2(200) PATH'./response/rujukan/peserta/informasi/prolanisPRB',
            CK_NMJENISPESERTA varchar2(200) PATH'./response/rujukan/peserta/jenisPeserta/keterangan',
            CK_KDJENISPESERTA varchar2(200) PATH'./response/rujukan/peserta/jenisPeserta/kode',
            CK_NOMR varchar2(200) PATH'./response/rujukan/peserta/mr/noMR',
            CK_NOTELEPON varchar2(200) PATH'./response/rujukan/peserta/mr/noTelepon',
            CK_NAMA varchar2(200) PATH'./response/rujukan/peserta/nama',
            CK_NIK varchar2(200) PATH'./response/rujukan/peserta/nik',
            CK_NOKARTU varchar2(200) PATH'./response/rujukan/peserta/noKartu',
            CK_PISA varchar2(200) PATH'./response/rujukan/peserta/pisa',
            CK_KDPROVIDER varchar2(200) PATH'./response/rujukan/peserta/provUmum/kdProvider',
            CK_NMPROVIDER varchar2(200) PATH'./response/rujukan/peserta/provUmum/nmProvider',
            CK_SEX varchar2(200) PATH'./response/rujukan/peserta/sex',
            CK_KETERANGAN varchar2(200) PATH'./response/rujukan/peserta/statusPeserta/keterangan',
            CK_KODE varchar2(200) PATH'./response/rujukan/peserta/statusPeserta/kode',
            CK_TGLCETAKKARTU varchar2(200) PATH'./response/rujukan/peserta/tglCetakKartu',
            CK_TGLLAHIR varchar2(200) PATH'./response/rujukan/peserta/tglLahir',
            CK_TGLTAT varchar2(200) PATH'./response/rujukan/peserta/tglTAT',
            CK_TGLTMT varchar2(200) PATH'./response/rujukan/peserta/tglTMT',
            CK_UMURSAATPELAYANAN varchar2(200) PATH'./response/rujukan/peserta/umur/umurSaatPelayanan',
            CK_UMURSEKARANG varchar2(200) PATH'./response/rujukan/peserta/umur/umurSekarang',
CR_DIAGNOSA_AWAL varchar2(200) PATH'./response/rujukan/diagnosa/kode',
CR_DIAGNOSA_AWAL_DESC varchar2(200) PATH'./response/rujukan/diagnosa/nama',
CR_noKunjungan varchar2(200) PATH'./response/rujukan/noKunjungan',
CR_pelayanan  varchar2(200) PATH'./response/rujukan/pelayanan/kode',
CR_pelayanan_desc varchar2(200) PATH'./response/rujukan/pelayanan/nama',
CR_poliRujukan varchar2(200) PATH'./response/rujukan/poliRujukan/kode',
CR_poliRujukan_desc varchar2(200) PATH'./response/rujukan/poliRujukan/nama',
CR_provPerujuk varchar2(200) PATH'./response/rujukan/provPerujuk/kode',
CR_provPerujuk_desc varchar2(200) PATH'./response/rujukan/provPerujuk/nama',
CR_tglKunjungan varchar2(200) PATH'./response/rujukan/tglKunjungan'
     ) xmlt       )
    LOOP
--insert xml value into table
-- insert cari rujukan
		insert into BRMST_CARI_RUJUKANS(CR_pk,CR_code,CR_message,DIAGNOSA_AWAL,DIAGNOSA_AWAL_DESC,no_rujukan,JENIS_PELAYANAN,POLI_TUJUAN,POLI_TUJUAN_DESC,TGL_RUJUKAN)
		values(vckpk,i.CK_code,i.CK_message,i.CR_DIAGNOSA_AWAL,i.CR_DIAGNOSA_AWAL_DESC,i.CR_noKunjungan,i.CR_pelayanan,i.CR_poliRujukan,i.CR_poliRujukan_desc,i.CR_tglKunjungan);
 COMMIT;
--insert cari ktsp
			insert into BRMST_CARI_KTSPS
			(CK_pk,
			CK_code,
			CK_message,
			CK_dinsos,
			CK_iuran,
			CK_noSKTM,
			CK_prolanisPRB,
			CK_kdJenisPeserta,
			CK_nmJenisPeserta,
			CK_kdKelas,
			CK_nmKelas,
			CK_nama,
			CK_nik,
			CK_noKartu,
			CK_noMr,
			CK_pisa,
			CK_kdCabang,
			CK_kdProvider,
			CK_nmCabang,
			CK_nmProvider,
			CK_sex,
			CK_keterangan,
			CK_kode,
			CK_tglCetakKartu,
			CK_tglLahir,
			CK_tglTAT,
			CK_tglTMT,
			CK_umurSaatPelayanan,
			CK_umurSekarang,
			CK_noTelepon)
			values
			(vckpk,
			i.CK_code,
			i.CK_message,
			i.CK_dinsos,
			null,
			i.CK_noSKTM,
			i.CK_prolanisPRB,
			i.CK_kdJenisPeserta,
			i.CK_nmJenisPeserta,
			i.CK_kdKelas,
			i.CK_nmKelas,
			i.CK_nama,
			i.CK_nik,
			i.CK_noKartu,
			i.CK_noMr,
			i.CK_pisa,
			null,
			i.CK_kdProvider,
			null,
			i.CK_nmProvider,
			i.CK_sex,
			i.CK_keterangan,
			i.CK_kode,
			i.CK_tglCetakKartu,
			i.CK_tglLahir,
			i.CK_tglTAT,
			i.CK_tglTMT,
			i.CK_umurSaatPelayanan,
			i.CK_umurSekarang,
			i.CK_noTelepon);
    END LOOP;
    COMMIT;
END brproc_cari_norujukanNOKA;
/

-- ==== PROCEDURE BRPROC_CARI_SEP ====
CREATE OR REPLACE PROCEDURE "SIKLIK"."BRPROC_CARI_SEP" (vsep in varchar2,vcrpk in varchar2)
as
--var base url
    vbase_consid varchar2(100);
    vbase_secretkey varchar2(100);
    vbase_url varchar2(4000);
    vbase_url_jadi varchar2(4000);
    vbase_url_local varchar2(4000);
    vbase_nokartu varchar2(100);
--var utl http
    req utl_http.req;
    resp utl_http.resp;
    value1 clob;
    Lvalue1 number;
    vresult clob;
    v_url varchar2(4000);
    v_post varchar2(4000);
--var coloumns
cariSEPcode varchar2(100);
cariSEPmessage varchar2(1000);
vcode varchar2(100);
vmessage varchar2(1000);
vPPKPerujuk varchar2(100);
vcatatan varchar2(100);
vdiagnosa varchar2(100);
vjnsPelayanan varchar2(100);
vkelasRawat varchar2(100);
vnoSep varchar2(100);
vpenjamin varchar2(100);
vasuransi varchar2(100);
vhakKelas varchar2(100);
vjnsPeserta varchar2(100);
vkelamin varchar2(100);
vnama varchar2(100);
vnoKartu varchar2(100);
vnoMr varchar2(100);
vtglLahir varchar2(100);
vpoli varchar2(100);
vpoliEksekutif varchar2(100);
vtglSep varchar2(100);
BEGIN
--find base url
        select base_url,base_url_local,base_consid,base_secretkey into vbase_url,vbase_url_local,vbase_consid,vbase_secretkey from BRMST_BASEBRIDSEPS;
        vbase_url_jadi:=vbase_url||'/SEP/'||vsep;
--post var value
             v_post:='consid='||vbase_consid||'&'||'secretkey='||vbase_secretkey||'&'||'url='||vbase_url_jadi;
             v_url:=vbase_url_local||'GetSEP';
             --v_url:='http://localhost/bridgingbpjs/contohpeserta.xml';
             req := utl_http.begin_request(v_url,'POST');
             UTL_HTTP.SET_HEADER(req,'User-Agent','Mozilla/4.0');
             UTL_HTTP.SET_HEADER (req,'Content-Type','application/x-www-form-urlencoded');
             UTL_HTTP.SET_HEADER (req,'Content-Length',length(v_post));
             UTL_HTTP.WRITE_TEXT (req,v_post);
             resp := utl_http.get_response(req);
                vresult := EMPTY_CLOB;
       --get length clob
        select dbms_lob.getlength(value1) into Lvalue1 from dual;
            LOOP
              UTL_HTTP.READ_TEXT(resp, value1, Lvalue1);
              vresult := vresult || value1;
            END LOOP;
               utl_http.end_response(resp);
             EXCEPTION
               WHEN utl_http.end_of_body THEN
               utl_http.end_response(resp);
               --dbms_output.put_line(vresult);
--extrack xml's value
--extracting multiple node from xml
--cek jika gagal mendapatkan nomer sep
 select extractvalue(xmltype.createxml(vresult),'/myroot/metaData/code'),
 extractvalue(xmltype.createxml(vresult),'/myroot/metaData/message')
 into
cariSEPcode,
cariSEPmessage
from dual;
                if cariSEPcode!='200' then
                   insert into  BRMST_CARISEPS(KD_CARISEP,cs_code,cs_message)values(vcrpk,cariSEPcode,cariSEPcode||cariSEPmessage);
                   else
                   select extractvalue(xmltype.createxml(vresult),'/myroot/metaData/code'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/metaData/message'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/PPKPerujuk'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/catatan'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/diagnosa'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/jnsPelayanan'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/kelasRawat'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/noSep'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/penjamin'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/peserta/asuransi'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/peserta/hakKelas'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/peserta/jnsPeserta'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/peserta/kelamin'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/peserta/nama'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/peserta/noKartu'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/peserta/noMr'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/peserta/tglLahir'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/poli'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/poliEksekutif'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/tglSep')
                   into 
                    vcode, 
                    vmessage, 
                    vPPKPerujuk, 
                    vcatatan, 
                    vdiagnosa, 
                    vjnsPelayanan, 
                    vkelasRawat, 
                    vnoSep, 
                    vpenjamin, 
                    vasuransi, 
                    vhakKelas, 
                    vjnsPeserta, 
                    vkelamin, 
                    vnama, 
                    vnoKartu, 
                    vnoMr, 
                    vtglLahir, 
                    vpoli, 
                    vpoliEksekutif, 
                    vtglSep
                   from dual;
                    insert into BRMST_CARISEPS
                    (KD_CARISEP,
                    CS_code, 
                    CS_message, 
                    CS_PPKPerujuk, 
                    CS_catatan, 
                    CS_diagnosa, 
                    CS_jnsPelayanan, 
                    CS_kelasRawat, 
                    CS_noSep, 
                    CS_penjamin, 
                    CS_asuransi, 
                    CS_hakKelas, 
                    CS_jnsPeserta, 
                    CS_kelamin, 
                    CS_nama, 
                    CS_noKartu, 
                    CS_noMr, 
                    CS_tglLahir, 
                    CS_poli, 
                    CS_poliEksekutif, 
                    CS_tglSep)
                    values         
                    (vcrpk,
                    vcode, 
                    vmessage, 
                    vPPKPerujuk, 
                    vcatatan, 
                    vdiagnosa, 
                    vjnsPelayanan, 
                    vkelasRawat, 
                    vnoSep, 
                    vpenjamin, 
                    vasuransi, 
                    vhakKelas, 
                    vjnsPeserta, 
                    vkelamin, 
                    vnama, 
                    vnoKartu, 
                    vnoMr, 
                    vtglLahir, 
                    vpoli, 
                    vpoliEksekutif, 
                    vtglSep);                   
                   end if;
                   commit;
END brproc_cari_sep;
/

-- ==== PROCEDURE BRPROC_DELETERUJUKAN ====
CREATE OR REPLACE PROCEDURE "SIKLIK"."BRPROC_DELETERUJUKAN" (vnoRujukan in varchar2,vKDRUJUKANDELETE in varchar2)
as
--var base url
 vbase_consid varchar2(100);
 vbase_secretkey varchar2(100);
    vbase_ppkrs varchar2(100);
 vbase_url varchar2(4000);
 vbase_url_jadi varchar2(4000);
 vbase_url_local varchar2(4000);
--var utl http
 req utl_http.req;
 resp utl_http.resp;
    value1 clob;
    Lvalue1 number;
 vresult clob;
 v_url varchar2(4000);
 v_post varchar2(4000);
--var coloumns
 HAPUSRujukancode varchar2(100);
 HAPUSRujukanmessage varchar2(1000);
 HAPUSRujukanresponse varchar2(100);
BEGIN
--find base url
  select base_url,base_url_local,base_consid,base_secretkey,base_ppkrs into vbase_url,vbase_url_local,vbase_consid,vbase_secretkey,vbase_ppkrs from BRMST_BASEBRIDSEPS;
  vbase_url_jadi:=vbase_url||'Rujukan/delete';
--post var value
       v_post:='consid='||vbase_consid||'&'||'secretkey='||vbase_secretkey||'&'||'url='||vbase_url_jadi||'&'||'noRujukan='||vnoRujukan;
    v_url:=vbase_url_local||'HapusRujukan';
    req := utl_http.begin_request(v_url,'POST');
    UTL_HTTP.SET_HEADER(req,'User-Agent','Mozilla/4.0');
    UTL_HTTP.SET_HEADER (req,'Content-Type','application/x-www-form-urlencoded');
    UTL_HTTP.SET_HEADER (req,'Content-Length',length(v_post));
    UTL_HTTP.WRITE_TEXT (req,v_post);
    resp := utl_http.get_response(req);
       vresult := EMPTY_CLOB;
       --get length clob
        select dbms_lob.getlength(value1) into Lvalue1 from dual;
   LOOP
     UTL_HTTP.READ_TEXT(resp, value1, Lvalue1);
     vresult := vresult || value1;
   END LOOP;
      utl_http.end_response(resp);
    EXCEPTION
      WHEN utl_http.end_of_body THEN
      utl_http.end_response(resp);
--extrack xml's value
 select extractvalue(xmltype.createxml(vresult),'/myroot/metaData/code'),
 extractvalue(xmltype.createxml(vresult),'/myroot/metaData/message'),
 extractvalue(xmltype.createxml(vresult),'/myroot/response')
into
HAPUSRujukancode,
HAPUSRujukanmessage,
HAPUSRujukanresponse
from dual;
--insert xml value into table
   insert into BRMST_RujukandeleteS
   (KD_RUJUKANDELETE,
   RD_code,
   RD_message,
   RD_delete)
   values
   (vKDRUJUKANDELETE,
   HAPUSRujukancode,
   HAPUSRujukanmessage,
   HAPUSRujukanresponse);
    COMMIT;
END brproc_deleterujukan;
/

-- ==== PROCEDURE BRPROC_DELETESEP ====
CREATE OR REPLACE PROCEDURE "SIKLIK"."BRPROC_DELETESEP" (vnosep in varchar2,vhapusseppk in varchar2,vuserhapus in varchar2)
as
--var base url
 vbase_consid varchar2(100);
 vbase_secretkey varchar2(100);
    vbase_ppkrs varchar2(100);
 vbase_url varchar2(4000);
 vbase_url_jadi varchar2(4000);
 vbase_url_local varchar2(4000);
--var utl http
 req utl_http.req;
 resp utl_http.resp;
    value1 clob;
    Lvalue1 number;
 vresult clob;
 v_url varchar2(4000);
 v_post varchar2(4000);
--var coloumns
 HAPUSSEPcode varchar2(100);
 HAPUSSEPmessage varchar2(1000);
 HAPUSSEPresponse varchar2(100);
BEGIN
--find base url
  select base_url,base_url_local,base_consid,base_secretkey,base_ppkrs into vbase_url,vbase_url_local,vbase_consid,vbase_secretkey,vbase_ppkrs from BRMST_BASEBRIDSEPS;
  vbase_url_jadi:=vbase_url||'SEP/Delete';
--post var value
       v_post:='consid='||vbase_consid||'&'||'secretkey='||vbase_secretkey||'&'||'url='||vbase_url_jadi||'&'||'NoSEP='||vnosep||'&'||'user='||vuserhapus;
    v_url:=vbase_url_local||'HapusSEP';
    req := utl_http.begin_request(v_url,'POST');
    UTL_HTTP.SET_HEADER(req,'User-Agent','Mozilla/4.0');
    UTL_HTTP.SET_HEADER (req,'Content-Type','application/x-www-form-urlencoded');
    UTL_HTTP.SET_HEADER (req,'Content-Length',length(v_post));
    UTL_HTTP.WRITE_TEXT (req,v_post);
    resp := utl_http.get_response(req);
       vresult := EMPTY_CLOB;
       --get length clob
        select dbms_lob.getlength(value1) into Lvalue1 from dual;
   LOOP
     UTL_HTTP.READ_TEXT(resp, value1, Lvalue1);
     vresult := vresult || value1;
   END LOOP;
      utl_http.end_response(resp);
    EXCEPTION
      WHEN utl_http.end_of_body THEN
      utl_http.end_response(resp);
--extrack xml's value
 select extractvalue(xmltype.createxml(vresult),'/myroot/metaData/code'),
 extractvalue(xmltype.createxml(vresult),'/myroot/metaData/message'),
 extractvalue(xmltype.createxml(vresult),'/myroot/response')
into
HAPUSSEPcode,
HAPUSSEPmessage,
HAPUSSEPresponse
from dual;
--insert xml value into table
   insert into BRMST_HAPUSSEPS
   (HS_pk,
   HS_code,
   HS_message,
   HS_response)
   values
   (vhapusseppk,
   HAPUSSEPcode,
   HAPUSSEPmessage,
   HAPUSSEPresponse);
    COMMIT;
END brproc_deletesep;
/

-- ==== PROCEDURE BRPROC_DIAGNOSA ====
CREATE OR REPLACE PROCEDURE "SIKLIK"."BRPROC_DIAGNOSA" (vKodeatauNamaDiagnosa in varchar2)
as
--var base url
 vbase_consid varchar2(100);
 vbase_secretkey varchar2(100);
 vbase_url varchar2(4000);
 vbase_url_jadi varchar2(4000);
 vbase_url_local varchar2(4000);
--var utl http
 req utl_http.req;
 resp utl_http.resp;
 value1 clob;
 Lvalue1 number;
 vresult clob;
 v_url varchar2(4000);
 v_post varchar2(4000);
--var coloumns
 CKkodeDiagnosa    varchar2(100);
 CKnamaDiagnosa    varchar2(100);
BEGIN
--find base url
  select base_url,base_url_local,base_consid,base_secretkey into vbase_url,vbase_url_local,vbase_consid,vbase_secretkey from BRMST_BASEBRIDSEPS;
  vbase_url_jadi:=vbase_url||'referensi/diagnosa/'||vKodeatauNamaDiagnosa;
--post var value
  v_post:='consid='||vbase_consid||'&'||'secretkey='||vbase_secretkey||'&'||'url='||vbase_url_jadi;
    --v_url:='http://localhost/bridgingbpjs/contohpoli.xml';
    v_url:=vbase_url_local||'GetDiagnosa';
    req := utl_http.begin_request(v_url,'POST');
    UTL_HTTP.SET_HEADER(req,'User-Agent','Mozilla/4.0');
    UTL_HTTP.SET_HEADER (req,'Content-Type','application/x-www-form-urlencoded');
    UTL_HTTP.SET_HEADER (req,'Content-Length',length(v_post));
    UTL_HTTP.WRITE_TEXT (req,v_post);
    resp := utl_http.get_response(req);
       vresult := EMPTY_CLOB;
       --get length clob
        select dbms_lob.getlength(value1) into Lvalue1 from dual;
   LOOP
     UTL_HTTP.READ_TEXT(resp, value1, Lvalue1);
     vresult := vresult || value1;
   END LOOP;
      utl_http.end_response(resp);
    EXCEPTION
      WHEN utl_http.end_of_body THEN
      utl_http.end_response(resp);
-- delete log table BRMST_DIAGNOSAS
delete from BRMST_DIAGNOSAS;
commit;
--extrack xml's value
--extracting multiple node from xml
    FOR i IN
      (
select kodeDiagnosa,namaDiagnosa
FROM XMLTABLE('/myroot/response/diagnosa'
         PASSING
            xmltype(vresult)
         COLUMNS
            --describe columns and path to them:
            kodeDiagnosa  varchar2(120)    PATH './kode',
            namaDiagnosa varchar2(120)    PATH './nama'
     ) xmlt
       )
    LOOP
      --insert xml value into table
      insert into BRMST_DIAGNOSAS(kodeDiagnosa,namaDiagnosa)values(i.kodeDiagnosa,i.namaDiagnosa);
    END LOOP;
    COMMIT;
END brproc_diagnosa;
/

-- ==== PROCEDURE BRPROC_DPJP ====
CREATE OR REPLACE PROCEDURE "SIKLIK"."BRPROC_DPJP" (vpel in varchar2,vtglpel in varchar2,vsepesialis in varchar2)
as
--var base url
 vbase_consid varchar2(100);
 vbase_secretkey varchar2(100);
 vbase_url varchar2(4000);
 vbase_url_jadi varchar2(4000);
 vbase_url_local varchar2(4000);
--var utl http
 req utl_http.req;
 resp utl_http.resp;
 value1 clob;
 Lvalue1 number;
 vresult clob;
 v_url varchar2(4000);
 v_post varchar2(4000);
--var coloumns
 CKkdDPJP    varchar2(100);
 CKnmDPJP    varchar2(100);
BEGIN
--find base url
  select base_url,base_url_local,base_consid,base_secretkey into vbase_url,vbase_url_local,vbase_consid,vbase_secretkey from BRMST_BASEBRIDSEPS;
  vbase_url_jadi:=vbase_url||'referensi/dokter/pelayanan/'||vpel||'/tglPelayanan/'||vtglpel||'/Spesialis/'||vsepesialis;
--post var value
  v_post:='consid='||vbase_consid||'&'||'secretkey='||vbase_secretkey||'&'||'url='||vbase_url_jadi;
    --v_url:='http://localhost/bridgingbpjs/contohDPJP.xml';
    v_url:=vbase_url_local||'GetDPJP';
    req := utl_http.begin_request(v_url,'POST');
    UTL_HTTP.SET_HEADER(req,'User-Agent','Mozilla/4.0');
    UTL_HTTP.SET_HEADER (req,'Content-Type','application/x-www-form-urlencoded');
    UTL_HTTP.SET_HEADER (req,'Content-Length',length(v_post));
    UTL_HTTP.WRITE_TEXT (req,v_post);
    resp := utl_http.get_response(req);
       vresult := EMPTY_CLOB;
       --get length clob
        select dbms_lob.getlength(value1) into Lvalue1 from dual;
   LOOP
     UTL_HTTP.READ_TEXT(resp, value1, Lvalue1);
     vresult := vresult || value1;
   END LOOP;
      utl_http.end_response(resp);
    EXCEPTION
      WHEN utl_http.end_of_body THEN
      utl_http.end_response(resp);
-- delete log table BRMST_DPJPS
delete from BRMST_DPJPS;
commit;
--extrack xml's value
--extracting multiple node from xml
    FOR i IN
      (
select dpjp_code,dpjp_name
FROM XMLTABLE('/myroot/response/list'
         PASSING
            xmltype(vresult)
         COLUMNS
            --describe columns and path to them:
            dpjp_code  varchar2(120)    PATH './kode',
            dpjp_name varchar2(120)    PATH './nama'
     ) xmlt
       )
    LOOP
      --insert xml value into table
      insert into BRMST_DPJPS(dpjp_code,dpjp_name)values(i.dpjp_code,i.dpjp_name);
    END LOOP;
    COMMIT;
END brproc_DPJP;
/

-- ==== PROCEDURE BRPROC_FASKES ====
CREATE OR REPLACE PROCEDURE "SIKLIK"."BRPROC_FASKES" (vfaskes in varchar2,vjenisfaskes in varchar2)
as
--var base url
 vbase_consid varchar2(100);
 vbase_secretkey varchar2(100);
 vbase_url varchar2(4000);
 vbase_url_jadi varchar2(4000);
 vbase_url_local varchar2(4000);
--var utl http
 req utl_http.req;
 resp utl_http.resp;
 value1 clob;
 Lvalue1 number;
 vresult clob;
 v_url varchar2(4000);
 v_post varchar2(4000);
--var coloumns
 kdCabang varchar2(100);
 kdProvider varchar2(100);
 nmCabang varchar2(100);
 nmProvider varchar2(100);
BEGIN
--find base url
  select base_url,base_url_local,base_consid,base_secretkey into vbase_url,vbase_url_local,vbase_consid,vbase_secretkey from BRMST_BASEBRIDSEPS;
  vbase_url_jadi:=vbase_url||'referensi/faskes/'||vfaskes||'/'||vjenisfaskes;
--post var value
  v_post:='consid='||vbase_consid||'&'||'secretkey='||vbase_secretkey||'&'||'url='||vbase_url_jadi;
    --v_url:='http://localhost/bridgingbpjs/contohfaskes.xml';
    v_url:=vbase_url_local||'GetFaskes';
    req := utl_http.begin_request(v_url,'POST');
    UTL_HTTP.SET_HEADER(req,'User-Agent','Mozilla/4.0');
    UTL_HTTP.SET_HEADER (req,'Content-Type','application/x-www-form-urlencoded');
    UTL_HTTP.SET_HEADER (req,'Content-Length',length(v_post));
    UTL_HTTP.WRITE_TEXT (req,v_post);
    resp := utl_http.get_response(req);
       vresult := EMPTY_CLOB;
       --get length clob
        select dbms_lob.getlength(value1) into Lvalue1 from dual;
   LOOP
     UTL_HTTP.READ_TEXT(resp, value1, Lvalue1);
     vresult := vresult || value1;
   END LOOP;
      utl_http.end_response(resp);
    EXCEPTION
      WHEN utl_http.end_of_body THEN
      utl_http.end_response(resp);
-- delete log table BRMST_faskesS
delete from BRMST_faskesES;
commit;
--extrack xml's value
--extracting multiple node from xml
    FOR i IN
      (
select kdProvider,nmProvider
FROM XMLTABLE('/myroot/response/faskes'
         PASSING
            xmltype(vresult)
         COLUMNS
            --describe columns and path to them:
            kdProvider  varchar2(120)    PATH './kode',
            nmProvider varchar2(120)    PATH './nama'
     ) xmlt
       )
    LOOP
      --insert xml value into table
      insert into BRMST_faskesES(kdProvider,nmProvider)values(i.kdProvider,i.nmProvider);
    END LOOP;
    COMMIT;
END brproc_faskes;
/

-- ==== PROCEDURE BRPROC_INSERTRUJUKAN ====
CREATE OR REPLACE PROCEDURE "SIKLIK"."BRPROC_INSERTRUJUKAN" (vnoSep in varchar2,
vtglRujukan in varchar2,
vppkDirujuk in varchar2,
vjnsPelayanan in varchar2,
vcatatan in varchar2,
vdiagRujukan in varchar2,
vtipeRujukan in varchar2,
vpoliRujukan in varchar2,
vKDRUJUKAN in varchar2
)
as
--var base url
 vbase_consid varchar2(100);
 vbase_secretkey varchar2(100);
 vbase_ppkrs varchar2(100);
 vbase_url varchar2(4000);
 vbase_url_jadi varchar2(4000);
 vbase_url_local varchar2(4000);
--var utl http
 req utl_http.req;
 resp utl_http.resp;
 value1 clob;
 Lvalue1 number;
 vresult clob;
 v_url varchar2(4000);
 v_post varchar2(4000);
--var coloumns
  RPOLITUJUANNAMA varchar2(100);       
 RPOLITUJUANKODE varchar2(100);       
 RASALRUJUKAN varchar2(100);          
 RJTUJUANRUJUKANNAMA varchar2(100);
 RDIAGNOSANAMA varchar2(100);         
 RDIAGNOSAKODE varchar2(100);         
 RASALRUJUKANKODE varchar2(100);      
 RCATATAN varchar2(100);              
 RJNOKARTU varchar2(100);             
 RJCODE varchar2(100);                
 RJNOMR varchar2(100);                
 RASALRUJUKANNAMA varchar2(100);      
 RJTUJUANRUJUKANKODE varchar2(100);
 RTGLRUJUKAN varchar2(100);           
 RJNAMAPESERTA varchar2(100);         
 RNORUJUKAN varchar2(100);            
 RJUSER varchar2(100);                
 KDRUJUKAN varchar2(100);             
 RJPPKDIRUJUK varchar2(100);          
 RJHAKKELAS varchar2(100);            
 RJNSPELAYANAN varchar2(100);         
 RNOSEP varchar2(100);                
 RJJNSPESERTA varchar2(100);          
 RJMESSAGE varchar2(1000);             
 RTIPERUJUKAN varchar2(100);          
 RJTGLLAHIR varchar2(100);            
 RJKELAMIN varchar2(100);             
 RJASURANSI varchar2(100);            
BEGIN
--find base url
  select base_url,base_url_local,base_consid,base_secretkey,base_ppkrs into vbase_url,vbase_url_local,vbase_consid,vbase_secretkey,vbase_ppkrs from BRMST_BASEBRIDSEPS;
  vbase_url_jadi:=vbase_url||'Rujukan/insert';
--post var value
  v_post:='consid='||vbase_consid||'&'||'secretkey='||vbase_secretkey||'&'||'url='||vbase_url_jadi||'&'||'noSep='||vnoSep||'&'||'tglRujukan='||vtglRujukan||'&'||'ppkDirujuk='||vppkDirujuk||'&'||'jnsPelayanan='||vjnsPelayanan||'&'||'catatan='||vcatatan||'&'||'diagRujukan='||vdiagRujukan||'&'||'tipeRujukan='||vtipeRujukan||'&'||'poliRujukan='||vpoliRujukan;
    --v_url:='http://localhost/bridgingbpjs/contohpoli.xml';
    v_url:=vbase_url_local||'CreateRujukan';
    req := utl_http.begin_request(v_url,'POST');
    UTL_HTTP.SET_HEADER(req,'User-Agent','Mozilla/4.0');
    UTL_HTTP.SET_HEADER (req,'Content-Type','application/x-www-form-urlencoded');
    UTL_HTTP.SET_HEADER (req,'Content-Length',length(v_post));
    UTL_HTTP.WRITE_TEXT (req,v_post);
    resp := utl_http.get_response(req);
       vresult := EMPTY_CLOB;
       --get length clob
        select dbms_lob.getlength(value1) into Lvalue1 from dual;
   LOOP
     UTL_HTTP.READ_TEXT(resp, value1, Lvalue1);
     vresult := vresult || value1;
   END LOOP;
      utl_http.end_response(resp);
    EXCEPTION
      WHEN utl_http.end_of_body THEN
      utl_http.end_response(resp);
--dbms_output.put_line(vresult);
--extrack xml's value
--cek jika gagal mendapatkan nomer sep
 select extractvalue(xmltype.createxml(vresult),'/myroot/metaData/code'),
 extractvalue(xmltype.createxml(vresult),'/myroot/metaData/message')
 into
RJCODE,
RJMESSAGE
from dual;
           if RJCODE!='200' then
                    insert into BRMST_RUJUKANS
        			(KD_RUJUKAN,
        			RJ_code,
        			RJ_message)
                    values
        			(vKDRUJUKAN,
        			RJCODE,
        			RJMESSAGE);
           else
                 select extractvalue(xmltype.createxml(vresult),'/myroot/metaData/code'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/metaData/message'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/rujukan/AsalRujukan/kode'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/rujukan/AsalRujukan/nama'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/rujukan/diagnosa/kode'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/rujukan/diagnosa/nama'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/rujukan/noRujukan'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/rujukan/peserta/asuransi'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/rujukan/peserta/hakKelas'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/rujukan/peserta/jnsPeserta'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/rujukan/peserta/kelamin'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/rujukan/peserta/nama'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/rujukan/peserta/noKartu'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/rujukan/peserta/noMr'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/rujukan/peserta/tglLahir'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/rujukan/poliTujuan/kode'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/rujukan/poliTujuan/nama'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/rujukan/tglRujukan'),
				   extractvalue(xmltype.createxml(vresult),'/myroot/response/rujukan/tujuanRujukan/kode'),
				   extractvalue(xmltype.createxml(vresult),'/myroot/response/rujukan/tujuanRujukan/nama')
                into
                RJcode,
				RJmessage,
				RAsalRujukankode,
				RAsalRujukannama,
				Rdiagnosakode,
				Rdiagnosanama,
				RnoRujukan,
				RJasuransi,
				RJhakKelas,
				RJjnsPeserta,
				RJkelamin,
				RJnamaPeserta,
				RJnoKartu,
				RJnoMr,
				RJtglLahir,
				RpoliTujuankode,
				RpoliTujuannama,
				RtglRujukan,
				RJtujuanRujukankode,
				RJtujuanRujukannama
            from dual;    
            --insert xml value into table
            	insert into BRMST_RUJUKANS
            	(R_POLITUJUANNAMA,       
				R_POLITUJUANKODE,       
				RJ_TUJUANRUJUKANNAMA,
				R_DIAGNOSANAMA,         
				R_DIAGNOSAKODE,         
				R_ASALRUJUKANKODE,              
				RJ_NOKARTU,             
				RJ_CODE,                
				RJ_NOMR,                
				R_ASALRUJUKANNAMA,      
				RJ_TUJUANRUJUKANKODE,
				R_TGLRUJUKAN,           
				RJ_NAMAPESERTA,         
				R_NORUJUKAN,                         
				KD_RUJUKAN,                      
				RJ_HAKKELAS,                              
				RJ_JNSPESERTA,          
				RJ_MESSAGE,                      
				RJ_TGLLAHIR,            
				RJ_KELAMIN,             
				RJ_ASURANSI)
            	values
            	(RpoliTujuannama,
				RpoliTujuankode,
				RJtujuanRujukannama,
				Rdiagnosanama,
				Rdiagnosakode,
				RAsalRujukankode,
				RJnoKartu,
				RJcode,
				RJnoMr,
				RASALRUJUKANNAMA,
				RJTUJUANRUJUKANKODE,
				RTGLRUJUKAN,
				RJNAMAPESERTA,
				RNORUJUKAN,
				vKDRUJUKAN,
				RJHAKKELAS,
				RJJNSPESERTA,
				RJMESSAGE,
				RJTGLLAHIR,
				RJKELAMIN,
				RJASURANSI
				);
                        end if;
    COMMIT;
END brproc_insertrujukan;
/

-- ==== PROCEDURE BRPROC_INSERTSEP ====
CREATE OR REPLACE PROCEDURE "SIKLIK"."BRPROC_INSERTSEP" (vnoKartu    in varchar2,
vtglSep    in varchar2,
vtglRujukan    in varchar2,
vnoRujukan    in varchar2,
vppkRujukan    in varchar2,
vjnsPelayanan    in varchar2,
vcatatan    in varchar2,
vdiagAwal    in varchar2,
vpoliTujuan    in varchar2,
vklsRawat    in varchar2,
vlakaLantas    in varchar2,
vlokasiLaka    in varchar2,
vuser    in varchar2,
vnoMr    in varchar2,
vseppk    in varchar2,
vasalRujukan     in varchar2,
vcob     in varchar2,
vnoTelp     in varchar2,
veksekutif     in varchar2,
vpenjamin     in varchar2,
vs_kontrol in varchar2,
vdpjp_id in varchar2,
vtglKejadian_penjamin in varchar2,
vketerangan_penjamin in varchar2,
vsuplesi in varchar2,
vnoSepSuplesi in varchar2,
vkdPropinsi in varchar2,
vkdKabupaten in varchar2,
vkdKecamatan in varchar2,
vkatarak in varchar2
)
as
--var base url
 vbase_consid varchar2(100);
 vbase_secretkey varchar2(100);
 vbase_ppkrs varchar2(100);
 vbase_url varchar2(4000);
 vbase_url_jadi varchar2(4000);
 vbase_url_local varchar2(4000);
--var utl http
 req utl_http.req;
 resp utl_http.resp;
 value1 clob;
 Lvalue1 number;
 vresult clob;
 v_url varchar2(4000);
 v_post varchar2(4000);
--var coloumns
SEPcode varchar2(100);
SEPmessage varchar2(1000);
SEPresponse varchar2(100);
CATATAN varchar2(100);
DIAGNOSA varchar2(100);
JNSPELAYANAN varchar2(100);
PENJAMIN varchar2(100);
ASURANSI varchar2(100);
HAKKELAS varchar2(100);
JNSPESERTA varchar2(100);
KELAMIN varchar2(100);
NAMA varchar2(100);
NOKARTU varchar2(100);
NOMR varchar2(100);
TGLLAHIR varchar2(100);
POLI varchar2(100);
POLIEKSEKUTIF varchar2(100);
TGLSEP varchar2(100);
BEGIN
--find base url
  select base_url,base_url_local,base_consid,base_secretkey,base_ppkrs into vbase_url,vbase_url_local,vbase_consid,vbase_secretkey,vbase_ppkrs from BRMST_BASEBRIDSEPS;
  vbase_url_jadi:=vbase_url||'SEP/1.1/insert';
--post var value
  v_post:='consid='||vbase_consid||'&'||'secretkey='||vbase_secretkey||'&'||'url='||vbase_url_jadi||'&'||'noKartu='||vnoKartu||'&'||'tglSep='||vtglSep||'&'||'tglRujukan='||vtglRujukan||'&'||'noRujukan='||vnoRujukan||'&'||'ppkRujukan='||vppkRujukan||'&'||'ppkPelayanan='||vbase_ppkrs||'&'||'jnsPelayanan='||vjnsPelayanan||'&'||'catatan='||vcatatan||'&'||'diagAwal='||vdiagAwal||'&'||'poliTujuan='||vpoliTujuan||'&'||'klsRawat='||vklsRawat||'&'||'lakaLantas='||vlakaLantas
||'&'||'lokasiLaka='||vlokasiLaka||'&'||'user='||vuser||'&'||'noMr='||vnoMr||'&'||'asalRujukan='||vasalRujukan||'&'||'cob='||vcob||'&'||'noTelp='||vnoTelp||'&'||'eksekutif='||veksekutif||'&'||'penjamin='||vpenjamin||'&'||'noSurat='||vs_kontrol||'&'||'kodeDPJP='||vdpjp_id||'&'||'tglKejadian='||vtglKejadian_penjamin||'&'||'keterangan='||vketerangan_penjamin||'&'||'suplesi='||vsuplesi||'&'||'noSepSuplesi='||vnoSepSuplesi||'&'||'kdPropinsi='||vkdPropinsi||'&'||'kdKabupaten='||vkdKabupaten||'&'||'kdKecamatan='||vkdkecamatan||'&'||'katarak='||vkatarak;
    --v_url:='http://localhost/bridgingbpjs/contohpoli.xml';
    v_url:=vbase_url_local||'CreateSEP';
    req := utl_http.begin_request(v_url,'POST');
    UTL_HTTP.SET_HEADER(req,'User-Agent','Mozilla/4.0');
    UTL_HTTP.SET_HEADER (req,'Content-Type','application/x-www-form-urlencoded');
    UTL_HTTP.SET_HEADER (req,'Content-Length',length(v_post));
    UTL_HTTP.WRITE_TEXT (req,v_post);
    resp := utl_http.get_response(req);
       vresult := EMPTY_CLOB;
       --get length clob
        select dbms_lob.getlength(value1) into Lvalue1 from dual;
   LOOP
     UTL_HTTP.READ_TEXT(resp, value1, Lvalue1);
     vresult := vresult || value1;
   END LOOP;
      utl_http.end_response(resp);
    EXCEPTION
      WHEN utl_http.end_of_body THEN
      utl_http.end_response(resp);
--dbms_output.put_line(vresult);
--extrack xml's value
--cek jika gagal mendapatkan nomer sep
 select extractvalue(xmltype.createxml(vresult),'/myroot/metaData/code'),
 extractvalue(xmltype.createxml(vresult),'/myroot/metaData/message')
 into
SEPcode,
SEPmessage
from dual;
            if SEPcode!='200' then
                    insert into BRMST_SEPS
        			(SEP_pk,
        			SEP_code,
        			SEP_message)
                    values
        			(vseppk,
        			SEPcode,
        			SEPmessage);
           else
                 select extractvalue(xmltype.createxml(vresult),'/myroot/metaData/code'),
                 extractvalue(xmltype.createxml(vresult),'/myroot/metaData/message'),
                 extractvalue(xmltype.createxml(vresult),'/myroot/response/sep/noSep'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/sep/catatan'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/sep/diagnosa'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/sep/jnsPelayanan'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/sep/penjamin'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/sep/peserta/asuransi'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/sep/peserta/hakKelas'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/sep/peserta/jnsPeserta'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/sep/peserta/kelamin'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/sep/peserta/nama'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/sep/peserta/noKartu'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/sep/peserta/noMr'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/sep/peserta/tglLahir'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/sep/poli'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/sep/poliEksekutif'),
                   extractvalue(xmltype.createxml(vresult),'/myroot/response/sep/tglSep')
                into
                SEPcode,
                SEPmessage,
                SEPresponse,
                CATATAN
                ,DIAGNOSA
                ,JNSPELAYANAN
                ,PENJAMIN
                ,ASURANSI
                ,HAKKELAS
                ,JNSPESERTA
                ,KELAMIN
                ,NAMA
                ,NOKARTU
                ,NOMR
                ,TGLLAHIR
                ,POLI
                ,POLIEKSEKUTIF
                ,TGLSEP
            from dual;    
            --insert xml value into table
            			insert into BRMST_SEPS
            			(SEP_pk,
            			SEP_code,
            			SEP_message,
            			SEP_response,
                        CATATAN
                        ,DIAGNOSA
                        ,JNSPELAYANAN
                        ,PENJAMIN
                        ,ASURANSI
                        ,HAKKELAS
                        ,JNSPESERTA
                        ,KELAMIN
                        ,NAMA
                        ,NOKARTU
                        ,NOMR
                        ,TGLLAHIR
                        ,POLI
                        ,POLIEKSEKUTIF
                        ,TGLSEP)
            			values
            			(vseppk,
            			SEPcode,
            			SEPmessage,
            			SEPresponse,
                        CATATAN
                        ,DIAGNOSA
                        ,JNSPELAYANAN
                        ,PENJAMIN
                        ,ASURANSI
                        ,HAKKELAS
                        ,JNSPESERTA
                        ,KELAMIN
                        ,NAMA
                        ,NOKARTU
                        ,NOMR
                        ,TGLLAHIR
                        ,POLI
                        ,POLIEKSEKUTIF
                        ,TGLSEP);
                        end if;
    COMMIT;
END brproc_insertsep;
/

-- ==== PROCEDURE BRPROC_INSERT_TB ====
CREATE OR REPLACE PROCEDURE "SIKLIK"."BRPROC_INSERT_TB" (vitbpk in varchar2,
vid_tb_03 in varchar2,
vkd_pasien in varchar2,
vnik in varchar2,
vtipe_diagnosis in varchar2,
vkode_icd_x in varchar2,
vtgl_lahir in varchar2,
vtanggal_mulai_pengobatan in varchar2,
vtanggal_buat_laporan in varchar2,
vid_periode_laporan in varchar2,
vtahun_buat_laporan in varchar2)
as
--var base url
	vbase_rsid varchar2(100);
	vbase_rspass varchar2(100);
	vbase_url varchar2(4000);
	vbase_url_jadi varchar2(4000);
	vbase_url_local varchar2(4000);
--var utl http
	req utl_http.req;
	resp utl_http.resp;
    value1 clob;
    Lvalue1 number;
	vresult clob;
	v_url varchar2(4000);
	v_post varchar2(8000);
--var coloumns
	vresultstatus    varchar2(100);
	vresultid_tb_03    varchar2(100);
BEGIN
--find base url
		select base_url_tb,base_url_local,base_rsid,base_rspass into vbase_url,vbase_url_local,vbase_rsid,vbase_rspass from BRMST_BASEBRIDSEPS;
		vbase_url_jadi:=vbase_url||'insert_simrs.php';
--post var value
		     v_post:='rsid='||vbase_rsid||'&'||'rspass='||vbase_rspass||'&'||'url='||vbase_url_jadi||'&'||'vid_tb_03='||vid_tb_03||'&'||'vkd_pasien='||vkd_pasien||'&'||'vkd_fasyankes='||vbase_rsid||'&'||'vnik='||vnik||'&'||'vtipe_diagnosis='||vtipe_diagnosis||'&'||'vkode_icd_x='||vkode_icd_x||'&'||'vtgl_lahir='||vtgl_lahir||'&'||'vtanggal_mulai_pengobatan='||vtanggal_mulai_pengobatan||'&'||'vtanggal_buat_laporan='||vtanggal_buat_laporan||'&'||'vid_periode_laporan='||vid_periode_laporan||'&'||'vtahun_buat_laporan='||vtahun_buat_laporan;
			 v_url:=vbase_url_local||'InsertTB';
             --v_url:='http://localhost/bridgingbpjs/contohpeserta.xml';
			 req := utl_http.begin_request(v_url,'POST');
			 UTL_HTTP.SET_HEADER(req,'User-Agent','Mozilla/4.0');
			 UTL_HTTP.SET_HEADER (req,'Content-Type','application/x-www-form-urlencoded');
			 UTL_HTTP.SET_HEADER (req,'Content-Length',length(v_post));
			 UTL_HTTP.WRITE_TEXT (req,v_post);
			 resp := utl_http.get_response(req);
			           vresult := EMPTY_CLOB;
       --get length clob
        select dbms_lob.getlength(value1) into Lvalue1 from dual;
   LOOP
     UTL_HTTP.READ_TEXT(resp, value1, Lvalue1);
     vresult := vresult || value1;
   END LOOP;
      utl_http.end_response(resp);
    EXCEPTION
      WHEN utl_http.end_of_body THEN
      utl_http.end_response(resp);
--extrack xml's value
 select extractvalue(xmltype.createxml(vresult),'/myroot/status'),
 extractvalue(xmltype.createxml(vresult),'/myroot/id_tb_03')
into
vresultstatus,
vresultid_tb_03
from dual;
--insert xml value into table
   insert into BRMST_INSERTTBSTATUSES(ITB_PK,ITB_STATUS,ID_TB_03)values(vitbpk,vresultstatus,vresultid_tb_03);
    COMMIT;
END brproc_insert_TB;
/

-- ==== PROCEDURE BRPROC_KABUPATEN ====
CREATE OR REPLACE PROCEDURE "SIKLIK"."BRPROC_KABUPATEN" (vkabupaten in varchar2)
as
--var base url
 vbase_consid varchar2(100);
 vbase_secretkey varchar2(100);
 vbase_url varchar2(4000);
 vbase_url_jadi varchar2(4000);
 vbase_url_local varchar2(4000);
--var utl http
 req utl_http.req;
 resp utl_http.resp;
 value1 clob;
 Lvalue1 number;
 vresult clob;
 v_url varchar2(4000);
 v_post varchar2(4000);
--var coloumns
 CKkdkabupaten    varchar2(100);
 CKnmkabupaten    varchar2(100);
BEGIN
--find base url
  select base_url,base_url_local,base_consid,base_secretkey into vbase_url,vbase_url_local,vbase_consid,vbase_secretkey from BRMST_BASEBRIDSEPS;
  vbase_url_jadi:=vbase_url||'referensi/kabupaten/propinsi/'||vkabupaten;
--post var value
  v_post:='consid='||vbase_consid||'&'||'secretkey='||vbase_secretkey||'&'||'url='||vbase_url_jadi;
    --v_url:='http://localhost/bridgingbpjs/contohkabupaten.xml';
    v_url:=vbase_url_local||'GetKabupaten';
    req := utl_http.begin_request(v_url,'POST');
    UTL_HTTP.SET_HEADER(req,'User-Agent','Mozilla/4.0');
    UTL_HTTP.SET_HEADER (req,'Content-Type','application/x-www-form-urlencoded');
    UTL_HTTP.SET_HEADER (req,'Content-Length',length(v_post));
    UTL_HTTP.WRITE_TEXT (req,v_post);
    resp := utl_http.get_response(req);
       vresult := EMPTY_CLOB;
       --get length clob
        select dbms_lob.getlength(value1) into Lvalue1 from dual;
   LOOP
     UTL_HTTP.READ_TEXT(resp, value1, Lvalue1);
     vresult := vresult || value1;
   END LOOP;
      utl_http.end_response(resp);
    EXCEPTION
      WHEN utl_http.end_of_body THEN
      utl_http.end_response(resp);
-- delete log table BRMST_kabupatenS
delete from BRMST_kabupatenS;
commit;
--extrack xml's value
--extracting multiple node from xml
    FOR i IN
      (
select kdkabupaten,nmkabupaten
FROM XMLTABLE('/myroot/response/list'
         PASSING
            xmltype(vresult)
         COLUMNS
            --describe columns and path to them:
            kdkabupaten  varchar2(120)    PATH './kode',
            nmkabupaten varchar2(120)    PATH './nama'
     ) xmlt
       )
    LOOP
      --insert xml value into table
      insert into BRMST_kabupatenS(kode_kab,nama_kab)values(i.kdkabupaten,i.nmkabupaten);
    END LOOP;
    COMMIT;
END brproc_kabupaten;
/

-- ==== PROCEDURE BRPROC_KECAMATAN ====
CREATE OR REPLACE PROCEDURE "SIKLIK"."BRPROC_KECAMATAN" (vkecamatan in varchar2)
as
--var base url
 vbase_consid varchar2(100);
 vbase_secretkey varchar2(100);
 vbase_url varchar2(4000);
 vbase_url_jadi varchar2(4000);
 vbase_url_local varchar2(4000);
--var utl http
 req utl_http.req;
 resp utl_http.resp;
 value1 clob;
 Lvalue1 number;
 vresult clob;
 v_url varchar2(4000);
 v_post varchar2(4000);
--var coloumns
 CKkdkecamatan    varchar2(100);
 CKnmkecamatan    varchar2(100);
BEGIN
--find base url
  select base_url,base_url_local,base_consid,base_secretkey into vbase_url,vbase_url_local,vbase_consid,vbase_secretkey from BRMST_BASEBRIDSEPS;
  vbase_url_jadi:=vbase_url||'referensi/kecamatan/kabupaten/'||vkecamatan;
--post var value
  v_post:='consid='||vbase_consid||'&'||'secretkey='||vbase_secretkey||'&'||'url='||vbase_url_jadi;
    --v_url:='http://localhost/bridgingbpjs/contohkecamatan.xml';
    v_url:=vbase_url_local||'GetKecamatan';
    req := utl_http.begin_request(v_url,'POST');
    UTL_HTTP.SET_HEADER(req,'User-Agent','Mozilla/4.0');
    UTL_HTTP.SET_HEADER (req,'Content-Type','application/x-www-form-urlencoded');
    UTL_HTTP.SET_HEADER (req,'Content-Length',length(v_post));
    UTL_HTTP.WRITE_TEXT (req,v_post);
    resp := utl_http.get_response(req);
       vresult := EMPTY_CLOB;
       --get length clob
        select dbms_lob.getlength(value1) into Lvalue1 from dual;
   LOOP
     UTL_HTTP.READ_TEXT(resp, value1, Lvalue1);
     vresult := vresult || value1;
   END LOOP;
      utl_http.end_response(resp);
    EXCEPTION
      WHEN utl_http.end_of_body THEN
      utl_http.end_response(resp);
-- delete log table BRMST_kecamatanS
delete from BRMST_kecamatanS;
commit;
--extrack xml's value
--extracting multiple node from xml
    FOR i IN
      (
select kdkecamatan,nmkecamatan
FROM XMLTABLE('/myroot/response/list'
         PASSING
            xmltype(vresult)
         COLUMNS
            --describe columns and path to them:
            kdkecamatan  varchar2(120)    PATH './kode',
            nmkecamatan varchar2(120)    PATH './nama'
     ) xmlt
       )
    LOOP
      --insert xml value into table
      insert into BRMST_kecamatanS(kode_kec,nama_kec)values(i.kdkecamatan,i.nmkecamatan);
    END LOOP;
    COMMIT;
END brproc_kecamatan;
/

-- ==== PROCEDURE BRPROC_POLI ====
CREATE OR REPLACE PROCEDURE "SIKLIK"."BRPROC_POLI" (vcaripoli in varchar2)
as
--var base url
 vbase_consid varchar2(100);
 vbase_secretkey varchar2(100);
 vbase_url varchar2(4000);
 vbase_url_jadi varchar2(4000);
 vbase_url_local varchar2(4000);
--var utl http
 req utl_http.req;
 resp utl_http.resp;
 value1 clob;
 Lvalue1 number;
 vresult clob;
 v_url varchar2(4000);
 v_post varchar2(4000);
--var coloumns
 CKkdPoli    varchar2(100);
 CKnmPoli    varchar2(100);
BEGIN
--find base url
  select base_url,base_url_local,base_consid,base_secretkey into vbase_url,vbase_url_local,vbase_consid,vbase_secretkey from BRMST_BASEBRIDSEPS;
  vbase_url_jadi:=vbase_url||'referensi/poli/'||vcaripoli;
--post var value
  v_post:='consid='||vbase_consid||'&'||'secretkey='||vbase_secretkey||'&'||'url='||vbase_url_jadi;
    --v_url:='http://localhost/bridgingbpjs/contohpoli.xml';
    v_url:=vbase_url_local||'GetPoli';
    req := utl_http.begin_request(v_url,'POST');
    UTL_HTTP.SET_HEADER(req,'User-Agent','Mozilla/4.0');
    UTL_HTTP.SET_HEADER (req,'Content-Type','application/x-www-form-urlencoded');
    UTL_HTTP.SET_HEADER (req,'Content-Length',length(v_post));
    UTL_HTTP.WRITE_TEXT (req,v_post);
    resp := utl_http.get_response(req);
       vresult := EMPTY_CLOB;
       --get length clob
        select dbms_lob.getlength(value1) into Lvalue1 from dual;
   LOOP
     UTL_HTTP.READ_TEXT(resp, value1, Lvalue1);
     vresult := vresult || value1;
   END LOOP;
      utl_http.end_response(resp);
    EXCEPTION
      WHEN utl_http.end_of_body THEN
      utl_http.end_response(resp);
-- delete log table BRMST_POLIS
delete from BRMST_POLIS;
commit;
--extrack xml's value
--extracting multiple node from xml
    FOR i IN
      (
select kdPoli,nmPoli
FROM XMLTABLE('/myroot/response/poli'
         PASSING
            xmltype(vresult)
         COLUMNS
            --describe columns and path to them:
            kdPoli  varchar2(120)    PATH './kode',
            nmPoli varchar2(120)    PATH './nama'
     ) xmlt
       )
    LOOP
      --insert xml value into table
      insert into BRMST_POLIS(kdpoli,nmPoli)values(i.kdPoli,i.nmPoli);
    END LOOP;
    COMMIT;
END brproc_poli;
/

-- ==== PROCEDURE BRPROC_PROPINSI ====
CREATE OR REPLACE PROCEDURE "SIKLIK"."BRPROC_PROPINSI" 
as
--var base url
 vbase_consid varchar2(100);
 vbase_secretkey varchar2(100);
 vbase_url varchar2(4000);
 vbase_url_jadi varchar2(4000);
 vbase_url_local varchar2(4000);
--var utl http
 req utl_http.req;
 resp utl_http.resp;
 value1 clob;
 Lvalue1 number;
 vresult clob;
 v_url varchar2(4000);
 v_post varchar2(4000);
--var coloumns
 CKkdpropinsi    varchar2(100);
 CKnmpropinsi    varchar2(100);
BEGIN
--find base url
  select base_url,base_url_local,base_consid,base_secretkey into vbase_url,vbase_url_local,vbase_consid,vbase_secretkey from BRMST_BASEBRIDSEPS;
  vbase_url_jadi:=vbase_url||'referensi/propinsi';
--post var value
  v_post:='consid='||vbase_consid||'&'||'secretkey='||vbase_secretkey||'&'||'url='||vbase_url_jadi;
    --v_url:='http://localhost/bridgingbpjs/contohpropinsi.xml';
    v_url:=vbase_url_local||'GetPropinsi';
    req := utl_http.begin_request(v_url,'POST');
    UTL_HTTP.SET_HEADER(req,'User-Agent','Mozilla/4.0');
    UTL_HTTP.SET_HEADER (req,'Content-Type','application/x-www-form-urlencoded');
    UTL_HTTP.SET_HEADER (req,'Content-Length',length(v_post));
    UTL_HTTP.WRITE_TEXT (req,v_post);
    resp := utl_http.get_response(req);
       vresult := EMPTY_CLOB;
       --get length clob
        select dbms_lob.getlength(value1) into Lvalue1 from dual;
   LOOP
     UTL_HTTP.READ_TEXT(resp, value1, Lvalue1);
     vresult := vresult || value1;
   END LOOP;
      utl_http.end_response(resp);
    EXCEPTION
      WHEN utl_http.end_of_body THEN
      utl_http.end_response(resp);
-- delete log table BRMST_propinsiS
delete from BRMST_propinsiS;
commit;
--extrack xml's value
--extracting multiple node from xml
    FOR i IN
      (
select kdpropinsi,nmpropinsi
FROM XMLTABLE('/myroot/response/list'
         PASSING
            xmltype(vresult)
         COLUMNS
            --describe columns and path to them:
            kdpropinsi  varchar2(120)    PATH './kode',
            nmpropinsi varchar2(120)    PATH './nama'
     ) xmlt
       )
    LOOP
      --insert xml value into table
      insert into BRMST_propinsiS(kode_pro,nama_pro)values(i.kdpropinsi,i.nmpropinsi);
    END LOOP;
    COMMIT;
END brproc_propinsi;
/

-- ==== PROCEDURE BRPROC_RIWAYATPESERTA ====
CREATE OR REPLACE PROCEDURE "SIKLIK"."BRPROC_RIWAYATPESERTA" (vnokartu in varchar2,vrppk in varchar2)
as
--var base url
 vbase_consid varchar2(100);
 vbase_secretkey varchar2(100);
 vbase_url varchar2(4000);
 vbase_url_jadi varchar2(4000);
 vbase_url_local varchar2(4000);
--var utl http
 req utl_http.req;
 resp utl_http.resp;
 value1 clob;
 Lvalue1 number;
 vresult clob;
 v_url varchar2(4000);
 v_post varchar2(4000);
--var coloumns
vrpseq number:=1;
BEGIN
--find base url
  select base_url,base_url_local,base_consid,base_secretkey into vbase_url,vbase_url_local,vbase_consid,vbase_secretkey from BRMST_BASEBRIDSEPS;
  vbase_url_jadi:=vbase_url||'sep/peserta/';
--post var value
v_post:='consid='||vbase_consid||'&'||'secretkey='||vbase_secretkey||'&'||'url='||vbase_url_jadi||'&'||'nokartu='||vnokartu;
    --v_url:='http://localhost/bridgingbpjs/contohriwayatpeserta.xml';
    v_url:=vbase_url_local||'GetRiwayatPeserta';
    req := utl_http.begin_request(v_url,'POST');
    UTL_HTTP.SET_HEADER(req,'User-Agent','Mozilla/4.0');
    UTL_HTTP.SET_HEADER (req,'Content-Type','application/x-www-form-urlencoded');
    UTL_HTTP.SET_HEADER (req,'Content-Length',length(v_post));
    UTL_HTTP.WRITE_TEXT (req,v_post);
    resp := utl_http.get_response(req);
       vresult := EMPTY_CLOB;
       --get length clob
        select dbms_lob.getlength(value1) into Lvalue1 from dual;
   LOOP
     UTL_HTTP.READ_TEXT(resp, value1, Lvalue1);
     vresult := vresult || value1;
   END LOOP;
      utl_http.end_response(resp);
    EXCEPTION
      WHEN utl_http.end_of_body THEN
      utl_http.end_response(resp);
commit;
--extrack xml's value
--extracting multiple node from xml
    FOR i IN
      (
select biayaTagihan,kodeDiagnosa,namaDiagnosa,jnsPelayanan,noSEP,kdPoli,nmPoli,tglPulang,tglSEP
FROM XMLTABLE('/myroot/response/list'
         PASSING
            xmltype(vresult)
         COLUMNS
            --describe columns and path to them:
	    biayaTagihanxx  varchar2(120)    PATH './response/list/biayaTagihan',
            biayaTagihan  varchar2(120)    PATH './biayaTagihan',
            kodeDiagnosa varchar2(120)    PATH './diagnosa/kodeDiagnosa',
            namaDiagnosa varchar2(120)    PATH './diagnosa/namaDiagnosa',
            jnsPelayanan varchar2(120)    PATH './jnsPelayanan',
            noSEP varchar2(120)    PATH './noSEP',
            kdPoli varchar2(120)    PATH './poliTujuan/kdPoli',
            nmPoli varchar2(120)    PATH './poliTujuan/nmPoli',
            tglPulang varchar2(120)    PATH './tglPulang',
            tglSEP varchar2(120)    PATH './tglSEP'
     ) xmlt
       )
    LOOP
      --insert xml value into table
      insert into BRMST_riwayatpesertaS(RP_biayaTagihan,RP_kodeDiagnosa,RP_namaDiagnosa,RP_jnsPelayanan,RP_noSEP,RP_kdPoli,RP_nmPoli,RP_tglPulang,RP_tglSEP,RP_PK,RP_SEQ)
      values
      (i.biayaTagihan,i.kodeDiagnosa,i.namaDiagnosa,i.jnsPelayanan,i.noSEP,i.kdPoli,i.nmPoli,i.tglPulang,i.tglSEP,vrppk,vrpseq);
      vrpseq:=vrpseq+1;
    END LOOP;
    COMMIT;
END brproc_riwayatpeserta;
/

-- ==== PROCEDURE BRPROC_UPDATERUJUKAN ====
CREATE OR REPLACE PROCEDURE "SIKLIK"."BRPROC_UPDATERUJUKAN" (vnoRujukan in varchar2,
vppkDirujuk in varchar2,
vjnsPelayanan in varchar2,
vcatatan in varchar2,
vdiagRujukan in varchar2,
vtipeRujukan in varchar2,
vpoliRujukan in varchar2,
vKDRUJUKAN in varchar2
)
as
--var base url
 vbase_consid varchar2(100);
 vbase_secretkey varchar2(100);
 vbase_ppkrs varchar2(100);
 vbase_url varchar2(4000);
 vbase_url_jadi varchar2(4000);
 vbase_url_local varchar2(4000);
--var utl http
 req utl_http.req;
 resp utl_http.resp;
 value1 clob;
 Lvalue1 number;
 vresult clob;
 v_url varchar2(4000);
 v_post varchar2(4000);
--var coloumns
rujukancode varchar2(100);
rujukanmessage varchar2(1000);
rujukanresponse varchar2(100);
BEGIN
--find base url
  select base_url,base_url_local,base_consid,base_secretkey,base_ppkrs into vbase_url,vbase_url_local,vbase_consid,vbase_secretkey,vbase_ppkrs from BRMST_BASEBRIDSEPS;
  vbase_url_jadi:=vbase_url||'Rujukan/update';
--post var value
  v_post:='consid='||vbase_consid||'&'||'secretkey='||vbase_secretkey||'&'||'url='||vbase_url_jadi||'&'||'noRujukan='||vnoRujukan||'&'||'ppkDirujuk='||vppkDirujuk||'&'||'jnsPelayanan='||vjnsPelayanan||'&'||'catatan='||vcatatan||'&'||'diagRujukan='||vdiagRujukan||'&'||'tipeRujukan='||vtipeRujukan||'&'||'poliRujukan='||vpoliRujukan;
    v_url:=vbase_url_local||'UpdateRujukan';
    req := utl_http.begin_request(v_url,'POST');
    UTL_HTTP.SET_HEADER(req,'User-Agent','Mozilla/4.0');
    UTL_HTTP.SET_HEADER (req,'Content-Type','application/x-www-form-urlencoded');
    UTL_HTTP.SET_HEADER (req,'Content-Length',length(v_post));
    UTL_HTTP.WRITE_TEXT (req,v_post);
    resp := utl_http.get_response(req);
       vresult := EMPTY_CLOB;
       --get length clob
        select dbms_lob.getlength(value1) into Lvalue1 from dual;
   LOOP
     UTL_HTTP.READ_TEXT(resp, value1, Lvalue1);
     vresult := vresult || value1;
   END LOOP;
      utl_http.end_response(resp);
    EXCEPTION
      WHEN utl_http.end_of_body THEN
      utl_http.end_response(resp);
--extrack xml's value
 select extractvalue(xmltype.createxml(vresult),'/myroot/metaData/code'),
 extractvalue(xmltype.createxml(vresult),'/myroot/metaData/message'),
 extractvalue(xmltype.createxml(vresult),'/myroot/response')
into
rujukancode,
rujukanmessage,
rujukanresponse
from dual;    
--insert xml value into table
			insert into BRMST_RUJUKANUPDATES
			(KD_RUJUKANUPDATE,
			RU_code,
			RU_message,
			RU_response)
			values
			(vKDRUJUKAN,
			rujukancode,
			rujukanmessage,
			rujukanresponse);
    COMMIT;
END brproc_updaterujukan;
/

-- ==== PROCEDURE BRPROC_UPDATESEP ====
CREATE OR REPLACE PROCEDURE "SIKLIK"."BRPROC_UPDATESEP" (vnoKartu    in varchar2,
vtglSep    in varchar2,
vtglRujukan    in varchar2,
vnoRujukan    in varchar2,
vppkRujukan    in varchar2,
vjnsPelayanan    in varchar2,
vcatatan    in varchar2,
vdiagAwal    in varchar2,
vpoliTujuan    in varchar2,
vklsRawat    in varchar2,
vlakaLantas    in varchar2,
vlokasiLaka    in varchar2,
vuser    in varchar2,
vnoMr    in varchar2,
vupdateseppk    in varchar2,
vasalRujukan     in varchar2,
vcob     in varchar2,
vnoTelp     in varchar2,
veksekutif     in varchar2,
vpenjamin     in varchar2,
vnosep    in varchar2,
vkatarak    in varchar2,
vnoSurat    in varchar2,
vkodeDPJP   in varchar2,
vtglKejadian    in varchar2,
vketerangan in varchar2,
vsuplesi    in varchar2,
vnoSepSuplesi   in varchar2,
vkdPropinsi     in varchar2,
vkdKabupaten    in varchar2,
vkdKecamatan    in varchar2
)
as
--var base url
 vbase_consid varchar2(100);
 vbase_secretkey varchar2(100);
 vbase_ppkrs varchar2(100);
 vbase_url varchar2(4000);
 vbase_url_jadi varchar2(4000);
 vbase_url_local varchar2(4000);
--var utl http
 req utl_http.req;
 resp utl_http.resp;
 value1 clob;
 Lvalue1 number;
 vresult clob;
 v_url varchar2(4000);
 v_post varchar2(4000);
--var coloumns
SEPcode varchar2(100);
SEPmessage varchar2(1000);
SEPresponse varchar2(100);
BEGIN
--find base url
  select base_url,base_url_local,base_consid,base_secretkey,base_ppkrs into vbase_url,vbase_url_local,vbase_consid,vbase_secretkey,vbase_ppkrs from BRMST_BASEBRIDSEPS;
  vbase_url_jadi:=vbase_url||'SEP/1.1/Update';
--post var value
  v_post:='consid='||vbase_consid||'&'||'secretkey='||vbase_secretkey||'&'||'url='||vbase_url_jadi||'&'||'noKartu='||vnoKartu||'&'||'tglSep='||vtglSep||'&'||'tglRujukan='||vtglRujukan||'&'||'noRujukan='||vnoRujukan||'&'||'ppkRujukan='||vppkRujukan||'&'||'ppkPelayanan='||vbase_ppkrs||'&'||'jnsPelayanan='||vjnsPelayanan||'&'||'catatan='||vcatatan||'&'||'diagAwal='||vdiagAwal||'&'||'poliTujuan='||vpoliTujuan||'&'||'klsRawat='||vklsRawat||'&'||'lakaLantas='||vlakaLantas
||'&'||'lokasiLaka='||vlokasiLaka||'&'||'user='||vuser||'&'||'noMr='||vnoMr||'&'||'asalRujukan='||vasalRujukan||'&'||'cob='||vcob||'&'||'noTelp='||vnoTelp||'&'||'eksekutif='||veksekutif||'&'||'penjamin='||vpenjamin||'&'||'NoSEP='||vnosep||'&'||'suplesi='||vsuplesi||'&'||'noSepSuplesi='||vnosepsuplesi||'&'||'katarak='||vkatarak||'&'||'noSurat='||vnoSurat||'&'||'kodeDPJP='||vkodeDPJP||'&'||'tglKejadian='||vtglKejadian||'&'||'keterangan='||vketerangan||'&'||'kdPropinsi='||vkdPropinsi||'&'||'kdKabupaten='||vkdKabupaten||'&'||'kdKecamatan='||vkdKecamatan;
    v_url:=vbase_url_local||'UpdateSEP';
    req := utl_http.begin_request(v_url,'POST');
    UTL_HTTP.SET_HEADER(req,'User-Agent','Mozilla/4.0');
    UTL_HTTP.SET_HEADER (req,'Content-Type','application/x-www-form-urlencoded');
    UTL_HTTP.SET_HEADER (req,'Content-Length',length(v_post));
    UTL_HTTP.WRITE_TEXT (req,v_post);
    resp := utl_http.get_response(req);
       vresult := EMPTY_CLOB;
       --get length clob
        select dbms_lob.getlength(value1) into Lvalue1 from dual;
   LOOP
     UTL_HTTP.READ_TEXT(resp, value1, Lvalue1);
     vresult := vresult || value1;
   END LOOP;
      utl_http.end_response(resp);
    EXCEPTION
      WHEN utl_http.end_of_body THEN
      utl_http.end_response(resp);
--extrack xml's value
 select extractvalue(xmltype.createxml(vresult),'/myroot/metaData/code'),
 extractvalue(xmltype.createxml(vresult),'/myroot/metaData/message'),
 extractvalue(xmltype.createxml(vresult),'/myroot/response')
into
SEPcode,
SEPmessage,
SEPresponse
from dual;    
--insert xml value into table
			insert into BRMST_UPDATESEPS
			(US_pk,
			US_code,
			US_message,
			US_response)
			values
			(vupdateseppk,
			SEPcode,
			SEPmessage,
			SEPresponse);
    COMMIT;
END brproc_updatesep;
/

-- ==== PROCEDURE BRPROC_UPDATETGLPULANG ====
CREATE OR REPLACE PROCEDURE "SIKLIK"."BRPROC_UPDATETGLPULANG" (vnosep    in varchar2, vtglpulang    in varchar2, vupdatetglpulangpk in varchar2
)
as
--var base url
 vbase_consid varchar2(100);
 vbase_secretkey varchar2(100);
 vbase_ppkrs varchar2(100);
 vbase_url varchar2(4000);
 vbase_url_jadi varchar2(4000);
 vbase_url_local varchar2(4000);
--var utl http
 req utl_http.req;
 resp utl_http.resp;
 value1 clob;
 Lvalue1 number;
 vresult clob;
 v_url varchar2(4000);
 v_post varchar2(4000);
--var coloumns
updatetglpulangcode varchar2(100);
updatetglpulangmessage varchar2(1000);
updatetglpulangresponse varchar2(100);
BEGIN
--find base url
  select base_url,base_url_local,base_consid,base_secretkey,base_ppkrs into vbase_url,vbase_url_local,vbase_consid,vbase_secretkey,vbase_ppkrs from BRMST_BASEBRIDSEPS;
  vbase_url_jadi:=vbase_url||'Sep/updtglplg';
--post var value
  v_post:='consid='||vbase_consid||'&'||'secretkey='||vbase_secretkey||'&'||'url='||vbase_url_jadi||'&'||'tglPlg='||vtglpulang||'&'||'ppkPelayanan='||vbase_ppkrs||'&'||'NoSEP='||vnosep;
    v_url:=vbase_url_local||'Updatetglpulang';
    req := utl_http.begin_request(v_url,'POST');
    UTL_HTTP.SET_HEADER(req,'User-Agent','Mozilla/4.0');
    UTL_HTTP.SET_HEADER (req,'Content-Type','application/x-www-form-urlencoded');
    UTL_HTTP.SET_HEADER (req,'Content-Length',length(v_post));
    UTL_HTTP.WRITE_TEXT (req,v_post);
    resp := utl_http.get_response(req);
       vresult := EMPTY_CLOB;
       --get length clob
        select dbms_lob.getlength(value1) into Lvalue1 from dual;
   LOOP
     UTL_HTTP.READ_TEXT(resp, value1, Lvalue1);
     vresult := vresult || value1;
   END LOOP;
      utl_http.end_response(resp);
    EXCEPTION
      WHEN utl_http.end_of_body THEN
      utl_http.end_response(resp);
--extrack xml's value
 select extractvalue(xmltype.createxml(vresult),'/myroot/metaData/code'),
 extractvalue(xmltype.createxml(vresult),'/myroot/metaData/message'),
 extractvalue(xmltype.createxml(vresult),'/myroot/response')
into
updatetglpulangcode,
updatetglpulangmessage,
updatetglpulangresponse
from dual;
--insert xml value into table
            insert into BRMST_UPDATETGLPUANGS
            (TP_pk,
            TP_code,
            TP_message,
            TP_response)
            values
            (vupdatetglpulangpk,
            updatetglpulangcode,
            updatetglpulangmessage,
            updatetglpulangresponse);
    COMMIT;
END brproc_updatetglpulang;
/

-- ==== PROCEDURE CUSTOM_IMAGE_DISPLAY ====
CREATE OR REPLACE PROCEDURE "SIKLIK"."CUSTOM_IMAGE_DISPLAY" (p_image_id in number) 
						 as 
						   l_mime varchar2(255); 
						   l_length number; 
						   l_file_name varchar2(2000); 
						   lob_loc BLOB;
						 begin
						 
						 select mime_type, image, image_name, dbms_lob.getlength(image) 
						   into l_mime, lob_loc, l_file_name, l_length 
						   from demo_images where image_id = p_image_id;
						 
						 -- Set up HTTP header
						 -- Use an NVL around the mime type and  if it is a null, set it to
						 -- application/octect - which may launch a download window from windows 
						 owa_util.mime_header(nvl(l_mime,'application/octet'), FALSE ); 
						 
						 -- Set the size so the browser knows how much to download htp.p('Content-length: ' || l_length); 
						 
						 -- The filename will be used by the browser if the users does a "Save as" htp.p('Content-Disposition: filename="' || l_file_name || '"');
						 
						 -- Close the headers 
						 owa_util.http_header_close; 
						 
						 -- Download the BLOB 
						 wpg_docload.download_file( Lob_loc ); 
						 end;
/

