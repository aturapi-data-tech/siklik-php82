-- ============================================================
-- LANGKAH 3 — Recreate view dengan nama baru di dalam teksnya
-- Dibuat  : php artisan siklik:ddl-rename --prefix=SK  (2026-09-11 06:15)
-- Melepas ketergantungan view pada synonym. Wajib sebelum 05. Terminator "/" (teks view berisi komentar --), jalankan lewat SQL*Plus.
-- ============================================================
SET DEFINE OFF
SET SQLBLANKLINES ON
SET ECHO ON
WHENEVER SQLERROR EXIT SQL.SQLCODE


CREATE OR REPLACE VIEW SKVIEW_ACCOUNTS ("TXN_NAME", "TXN_ACC", "TXN_ACC_K", "TXN_DATE", "TXN_D", "TXN_K") AS
( 
-------BAYAR SLS CASH IN / PIUTANG SLS---------------
select 'BAYAR APOTEK NOTA No'||(select string_AGG(sls_no) from SKTXN_CASHINDTLS  where cashin_no=a.cashin_no),
b.acc_id,
(select acc_id from SKACC_CONFACCTXNS where conf_id='7')akun,
cashin_date,
cashin_value,
0 
from SKTXN_CASHINHDRS a,
SKACC_CARABAYARS b
where a.cb_id=b.cb_id
union all
select 'BAYAR PIUTANG APOTEK NOTA No'||(select string_AGG(sls_no) from SKTXN_CASHINDTLS  where cashin_no=a.cashin_no),
(select acc_id from SKACC_CONFACCTXNS where conf_id='7')akun,
b.acc_id,
cashin_date,
0,
cashin_value 
from SKTXN_CASHINHDRS a,
SKACC_CARABAYARS b
where a.cb_id=b.cb_id
----------------------
----------TRANSAKSI SLS CASH IN / PIUTANG------------
union all
select 'SLS TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='1')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='7')akun,
sls_date,
0,
(select nvl(sum(nvl(qty,0)*nvl(sales_price,0)),0)
from sktxn_slsdtls
where sls_no=a.sls_no)totalsls
 from SKTXN_SLSHDRS a
 where sls_status in ('H','L')
union all
select 'SLS PIUTANG TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='7')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='1')akun,
sls_date,
(select nvl(sum(nvl(qty,0)*nvl(sales_price,0)),0)
from sktxn_slsdtls
where sls_no=a.sls_no)totalsls,
0
 from SKTXN_SLSHDRS a
 where sls_status in ('H','L')
----------------------
-------DISKON SLS ITEM / PIUTANG---------------
union all
select 'SLS DISKON ITEM TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='5')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='7')akun,
sls_date,
(select nvl( sum(/**/((nvl(qty,0)*nvl(sales_price,0))*nvl(dtl_persen,0)/100)/**/+/**/nvl(dtl_diskon,0)/**/),0)
from sktxn_slsdtls
where sls_no=a.sls_no)totaldiskonitem,
0
from SKTXN_SLSHDRS a
where sls_status in ('H','L')
union all
select 'SLS DISKON ITEM TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='7')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='5')akun,
sls_date,
0,
(select nvl( sum(/**/((nvl(qty,0)*nvl(sales_price,0))*nvl(dtl_persen,0)/100)/**/+/**/nvl(dtl_diskon,0)/**/),0)
from sktxn_slsdtls
where sls_no=a.sls_no)totaldiskonitem
from SKTXN_SLSHDRS a
where sls_status in ('H','L')
----------------------
------DISKON SLS TOTAL / PIUTANG ----------------
union all
select 'SLS DISKON TOTAL TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='5')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='7')akun,
sls_date,
nvl(sls_diskon,0)totaldiskonall,
0
 from SKTXN_SLSHDRS a
 where sls_status in ('H','L')
union all
select 'SLS DISKON TOTAL TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='7')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='5')akun,
sls_date,
0,
nvl(sls_diskon,0)totaldiskonall
from SKTXN_SLSHDRS a
where sls_status in ('H','L') 
----------------------
----------------------
union all
-------BAYAR RCV CASH OUT / HUTANG RCV---------------
select 'BAYAR RCV CASH OUT',
b.acc_id,
(select acc_id from SKACC_CONFACCTXNS where conf_id='8')akun,
cashout_date,
0,
cashout_value 
from SKTXN_CASHOUTHDRS a,
SKACC_CARABAYARS b
where a.cb_id=b.cb_id
union all
select 'BAYAR HUTANG RCV',
(select acc_id from SKACC_CONFACCTXNS where conf_id='8')akun,
b.acc_id,
cashout_date,
cashout_value,
0 
from SKTXN_CASHOUTHDRS a,
SKACC_CARABAYARS b
where a.cb_id=b.cb_id
----------------------
----------TRANSAKSI RCV CASH OUT / HUTANG------------
union all
select 'RCV TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='2')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='8')akun,
RCV_date,
(select nvl(sum(nvl(qty,0)*nvl(cost_price,0)),0)
from sktxn_RCVdtls
where RCV_no=a.RCV_no)totalRCV,
0
 from SKTXN_RCVHDRS a
 where RCV_status in ('H','L')
union all
select 'RCV HUTANG TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='8')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='2')akun,
RCV_date,
0,
(select nvl(sum(nvl(qty,0)*nvl(cost_price,0)),0)
from sktxn_RCVdtls
where RCV_no=a.RCV_no)totalRCV
 from SKTXN_RCVHDRS a
 where RCV_status in ('H','L')
----------------------
-------DISKON RCV ITEM / HUTANG---------------
union all
select 'RCV DISKON ITEM TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='6')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='8')akun,
RCV_date,
0,
(select sum(nvl(qty,0)*nvl(cost_price,0))-
sum(
/*persen1*/(nvl(qty,0)*nvl(cost_price,0))/**/-/**/((nvl(qty,0)*nvl(cost_price,0))*nvl(dtl_persen,0)/100)/**/-/**/nvl(dtl_diskon,0)-
/*persen2*/(((nvl(qty,0)*nvl(cost_price,0))/**/-/**/((nvl(qty,0)*nvl(cost_price,0))*nvl(dtl_persen,0)/100)/**/-/**/nvl(dtl_diskon,0))*
(nvl(dtl_persen1,0)/100))-/**/nvl(dtl_diskon1,0))
from sktxn_RCVdtls
where RCV_no=a.RCV_no)totaldiskonitem
from SKTXN_RCVHDRS a
where RCV_status in ('H','L')
union all
select 'RCV DISKON ITEM TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='8')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='6')akun,
RCV_date,
(select sum(nvl(qty,0)*nvl(cost_price,0))-
sum(
/*persen1*/(nvl(qty,0)*nvl(cost_price,0))/**/-/**/((nvl(qty,0)*nvl(cost_price,0))*nvl(dtl_persen,0)/100)/**/-/**/nvl(dtl_diskon,0)-
/*persen2*/(((nvl(qty,0)*nvl(cost_price,0))/**/-/**/((nvl(qty,0)*nvl(cost_price,0))*nvl(dtl_persen,0)/100)/**/-/**/nvl(dtl_diskon,0))*
(nvl(dtl_persen1,0)/100))-/**/nvl(dtl_diskon1,0))
from sktxn_RCVdtls
where RCV_no=a.RCV_no)totaldiskonitem,
0
from SKTXN_RCVHDRS a
where RCV_status in ('H','L')
----------------------
------DISKON RCV TOTAL / HUTANG ----------------
union all
select 'RCV DISKON TOTAL TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='6')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='8')akun,
RCV_date,
0,
nvl(RCV_diskon,0)totaldiskonall
 from SKTXN_RCVHDRS a
 where RCV_status in ('H','L')
union all
select 'RCV DISKON TOTAL TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='8')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='6')akun,
RCV_date,
nvl(RCV_diskon,0)totaldiskonall,
0
from SKTXN_RCVHDRS a
where RCV_status in ('H','L')
and nvl(RCV_diskon,0)>0
----------------------
------RCV MATERAI / HUTANG ----------------
union all
select 'RCV MATERAI TOTAL TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='8')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='12')akun,
RCV_date,
0,
nvl(RCV_materai,0)totalmaterai
 from SKTXN_RCVHDRS a
 where RCV_status in ('H','L')
union all
select 'RCV MATERAI TOTAL TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='12')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='8')akun,
RCV_date,
nvl(RCV_materai,0)totalmaterai,
0
from SKTXN_RCVHDRS a
where RCV_status in ('H','L')
and nvl(RCV_materai,0)>0
----------------------
------RCV PPN / HUTANG ----------------
union all
select 'RCV PPN TOTAL TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='8')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='13')akun,
RCV_date,
0,
nvl(totalppn,0)total_ppn
 from SKVIEW_RCVHDRS a
 where RCV_status in ('H','L')
union all
select 'RCV PPN TOTAL TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='13')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='8')akun,
RCV_date,
nvl(totalppn,0)total_ppn,
0
from SKVIEW_RCVHDRS a
where RCV_status in ('H','L')
and nvl(totalppn,0)>0
----------------------
---------CASH OUT TU-------------
union all
select 'CASH OUT',
b.acc_id,
(select y.acc_id from SKTXN_TUCASHOUTS x,SKACC_CARABAYARS y where x.CB_ID=y.CB_ID and CO_STATUS='L' and CO_NO=a.co_no),
co_date,
nvl(co_nominal,0),
0
from SKTXN_TUCASHOUTS a,
SKACC_TUCICOS b
where a.TUCICO_ID=b.TUCICO_ID
and CO_STATUS='L'
union all
select 'CASH OUT',
b.acc_id,
(select y.acc_id from SKTXN_TUCASHOUTS x,SKACC_TUCICOS y where x.TUCICO_ID=y.TUCICO_ID and CO_STATUS='L' and CO_NO=a.co_no),
co_date,
0,
nvl(co_nominal,0)
from SKTXN_TUCASHOUTS a,
SKACC_CARABAYARS b
where a.CB_ID=b.CB_ID
and CO_STATUS='L' 
----------------------
---------CASH IN TU-------------
union all
select 'CASH IN',
b.acc_id,
(select y.acc_id from SKTXN_TUCASHINS x,SKACC_CARABAYARS y where x.CB_ID=y.CB_ID and CI_STATUS='L' and CI_NO=a.ci_no),
ci_date,
0,
nvl(ci_nominal,0)
from SKTXN_TUCASHINS a,
SKACC_TUCICOS b
where a.TUCIco_ID=b.TUCIco_ID
and ci_STATUS='L'
union all
select 'CASH IN',
b.acc_id,
(select y.acc_id from SKTXN_TUCASHINS x,SKACC_TUCICOS y where x.TUCICO_ID=y.TUCICO_ID and CI_STATUS='L' and CI_NO=a.ci_no),
ci_date,
nvl(ci_nominal,0),
0
from SKTXN_TUCASHINS a,
SKACC_CARABAYARS b
where a.CB_ID=b.CB_ID
and ci_STATUS='L' 
----------------------
union all
--RJ-----------------------------------------
-------BAYAR RJ CASH IN / PIUTANG RJ---------------
select 'BAYAR RJ '||RJC_DESC,
b.acc_id,
(select acc_id from SKACC_CONFACCTXNS where conf_id='7')akun,
RJC_DATE,
RJC_NOMINAL,
0 
from SKTXN_RJCASHINS a,
SKACC_CARABAYARS b
where a.cb_id=b.cb_id
union all
select 'BAYAR PIUTANG RJ '||RJC_DESC,
(select acc_id from SKACC_CONFACCTXNS where conf_id='7')akun,
b.acc_id,
RJC_DATE,
0,
RJC_NOMINAL 
from SKTXN_RJCASHINS a,
SKACC_CARABAYARS b
where a.cb_id=b.cb_id
--JD-----------------------------------------
union all
select 'RJ JD TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='1')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='7')akun,
rj_date,
0,
(select nvl(sum(ACCDOC_price),0)
from sktxn_rjaccdocs
where rj_no=a.rj_no)totalsls
from sktxn_rjhdrs a
where txn_status in ('H','L')
union all
select 'RJ JD PIUTANG TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='7')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='1')akun,
rj_date,
(select nvl(sum(ACCDOC_price),0)
from sktxn_rjaccdocs
where rj_no=a.rj_no)totalsls,
0
 from sktxn_rjhdrs a
 where txn_status in ('H','L')
--R OBATJ-----------------------------------------
union all
select 'RJ OBAT TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='1')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='7')akun,
rj_date,
0,
(select nvl(sum(qty*price),0)
from sktxn_rjobats
where rj_no=a.rj_no)totalsls
from sktxn_rjhdrs a
where txn_status in ('H','L')
union all
select 'RJ OBAT PIUTANG TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='7')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='1')akun,
rj_date,
(select nvl(sum(qty*price),0)
from sktxn_rjobats
where rj_no=a.rj_no)totalsls,
0
 from sktxn_rjhdrs a
 where txn_status in ('H','L')
--RJ JK-----------------------------------------
union all
select 'RJ JK TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='1')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='7')akun,
rj_date,
0,
(select nvl(sum(ACTE_price),0)
from SKTXN_RJACTEMPS
where rj_no=a.rj_no)totalsls
from sktxn_rjhdrs a
where txn_status in ('H','L')
union all
select 'RJ JK PIUTANG TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='7')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='1')akun,
rj_date,
(select nvl(sum(ACTE_price),0)
from SKTXN_RJACTEMPS
where rj_no=a.rj_no)totalsls,
0
 from sktxn_rjhdrs a
 where txn_status in ('H','L')
--RJ LAB-----------------------------------------
union all
select 'RJ LAB TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='1')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='7')akun,
rj_date,
0,
(select nvl(sum(lab_price),0)
from sktxn_rjlabs
where rj_no=a.rj_no)totalsls
from sktxn_rjhdrs a
where txn_status in ('H','L')
union all
select 'RJ LAB PIUTANG TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='7')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='1')akun,
rj_date,
(select nvl(sum(lab_price),0)
from sktxn_rjlabs
where rj_no=a.rj_no)totalsls,
0
 from sktxn_rjhdrs a
 where txn_status in ('H','L')
--RJ JM-----------------------------------------
union all
select 'RJ JM TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='1')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='7')akun,
rj_date,
0,
(select nvl(sum(pact_price),0)
from SKTXN_RJACTPARAMS
where rj_no=a.rj_no)totalsls
from sktxn_rjhdrs a
where txn_status in ('H','L')
union all
select 'RJ JM PIUTANG TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='7')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='1')akun,
rj_date,
(select nvl(sum(pact_price),0)
from SKTXN_RJACTPARAMS
where rj_no=a.rj_no)totalsls,
0
 from sktxn_rjhdrs a
 where txn_status in ('H','L')
--RJ RAD-----------------------------------------
union all
select 'RJ RAD TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='1')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='7')akun,
rj_date,
0,
(select nvl(sum(rad_price),0)
from sktxn_rjrads
where rj_no=a.rj_no)totalsls
from sktxn_rjhdrs a
where txn_status in ('H','L')
union all
select 'RJ RAD PIUTANG TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='7')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='1')akun,
rj_date,
(select nvl(sum(rad_price),0)
from sktxn_rjrads
where rj_no=a.rj_no)totalsls,
0
 from sktxn_rjhdrs a
 where txn_status in ('H','L')
--RJ LAIN-----------------------------------------
union all
select 'RJ LAIN TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='1')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='7')akun,
rj_date,
0,
(select nvl(sum(other_price),0)
from sktxn_rjothers
where rj_no=a.rj_no)totalsls
from sktxn_rjhdrs a
where txn_status in ('H','L')
union all
select 'RJ LAIN PIUTANG TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='7')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='1')akun,
rj_date,
(select nvl(sum(other_price),0)
from sktxn_rjothers
where rj_no=a.rj_no)totalsls,
0
 from sktxn_rjhdrs a
 where txn_status in ('H','L')
--RJ RJ ADMIN-----------------------------------------
union all
select 'RJ RJ ADMIN TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='1')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='7')akun,
rj_date,
0,
rj_admin
from sktxn_rjhdrs a
where txn_status in ('H','L')
union all
select 'RJ RJ ADMIN PIUTANG TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='7')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='1')akun,
rj_date,
rj_admin,
0
 from sktxn_rjhdrs a
 where txn_status in ('H','L')
--RJ RS ADMIN-----------------------------------------
union all
select 'RJ RS ADMIN TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='1')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='7')akun,
rj_date,
0,
rs_admin
from sktxn_rjhdrs a
where txn_status in ('H','L')
union all
select 'RJ RS ADMIN PIUTANG TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='7')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='1')akun,
rj_date,
rs_admin,
0
 from sktxn_rjhdrs a
 where txn_status in ('H','L')
------DISKON RJ TOTAL / PIUTANG ----------------
union all
select 'RJ DISKON TOTAL TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='5')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='7')akun,
rj_date,
nvl(RJ_DISKON,0)totaldiskonall,
0
 from SKTXN_RJHDRS a
 where txn_status in ('H','L')
union all
select 'RJ DISKON TOTAL TRANSAKSI',
(select acc_id from SKACC_CONFACCTXNS where conf_id='7')akun,
(select acc_id from SKACC_CONFACCTXNS where conf_id='5')akun,
rj_date,
0,
nvl(RJ_DISKON,0)totaldiskonall
from SKTXN_RJHDRS a
where txn_status in ('H','L') 
 )
/

CREATE OR REPLACE VIEW SKVIEW_ACCOUNTS_LABARUGI ("TXN_NAME", "TXN_ACC", "TXN_ACC_K", "TXN_DATE", "TXN_D", "TXN_K") AS
select TXN_NAME, TXN_ACC,TXN_ACC_K, TXN_DATE, TXN_D, TXN_K from SKVIEW_ACCOUNTS
---------HPP-------------
union all
select 'HPP',ACC_ID,(select acc_id from SKACC_CONFACCTXNS where conf_id='2')akun,to_date('01/12'||HPP_YEAR,'dd/mm/yyyy'),HPP,0 from SKVIEW_HPPES
union all
select 'HPP',(select acc_id from SKACC_CONFACCTXNS where conf_id='2')akun,ACC_ID,to_date('01/12'||HPP_YEAR,'dd/mm/yyyy'),0,HPP from SKVIEW_HPPES
/

CREATE OR REPLACE VIEW SKVIEW_ACCOUNTS_NERACA ("TXN_NAME", "TXN_ACC", "TXN_DATE", "TXN_D", "TXN_K") AS
select TXN_NAME, TXN_ACC, TXN_DATE, TXN_D, TXN_K from SKVIEW_ACCOUNTS_LABARUGI
union all
select 'LABARUGI BERJALAN',(select z.acc_id from SKACC_CONFACCTXNS z where conf_id='10'),to_date('01/12'||to_char(txn_date,'yyyy'),'dd/mm/yyyy'),0,sum(nvl(txn_k,0)-nvl(txn_d,0))
from skview_accounts_labarugi a,skacc_accountses b,skacc_gr_accountses c
where a.txn_acc=b.acc_id
and b.gra_id=c.gra_id
and gra_status='L'
and c.gra_id in('4','5')
and to_number(txn_d||txn_k)>0
group by to_char(txn_date,'yyyy')
/

CREATE OR REPLACE VIEW SKVIEW_ACCTEMPLATE ("GROUP_TEMP", "TEMP_ID", "TEMP_DTL_SEQ", "TEMP_ACC_SEQ", "TEMP_DTL_DESC", "ACC_ID", "ACC_DESC", "GRA_ID", "DK_STATUS", "GRA_STATUS") AS
select 1,TEMP_ID,TEMP_DTL_SEQ,TEMACC_SEQ,TEMP_DTL_DESC,b.ACC_ID,ACC_DESC,a.GRA_ID,(select DK_STATUS from skacc_gr_accountses where gra_id=a.gra_id),(select GRA_STATUS from skacc_gr_accountses where gra_id=a.gra_id)
from SKACC_TEMLABARUGINERACADTLS a,SKACC_TEMACCOUNTES b,
SKACC_ACCOUNTSES c
where a.temp_dtl=b.temp_dtl
and b.acc_id=c.acc_id
and a.gra_id='1'

union all
select 2,TEMP_ID,TEMP_DTL_SEQ,TEMACC_SEQ,TEMP_DTL_DESC,b.ACC_ID,ACC_DESC,a.GRA_ID,(select DK_STATUS from skacc_gr_accountses where gra_id=a.gra_id),(select GRA_STATUS from skacc_gr_accountses where gra_id=a.gra_id)
from SKACC_TEMLABARUGINERACADTLS a,SKACC_TEMACCOUNTES b,
SKACC_ACCOUNTSES c
where a.temp_dtl=b.temp_dtl
and b.acc_id=c.acc_id
and a.gra_id in ('2','3')

union all
select 3,TEMP_ID,TEMP_DTL_SEQ,TEMACC_SEQ,TEMP_DTL_DESC,b.ACC_ID,ACC_DESC,a.GRA_ID,(select DK_STATUS from skacc_gr_accountses where gra_id=a.gra_id),(select GRA_STATUS from skacc_gr_accountses where gra_id=a.gra_id)
from SKACC_TEMLABARUGINERACADTLS a,SKACC_TEMACCOUNTES b,
SKACC_ACCOUNTSES c
where a.temp_dtl=b.temp_dtl
and b.acc_id=c.acc_id
and a.gra_id='4'

union all
select 4,TEMP_ID,TEMP_DTL_SEQ,TEMACC_SEQ,TEMP_DTL_DESC,b.ACC_ID,ACC_DESC,a.GRA_ID,(select DK_STATUS from skacc_gr_accountses where gra_id=a.gra_id),(select GRA_STATUS from skacc_gr_accountses where gra_id=a.gra_id)
from SKACC_TEMLABARUGINERACADTLS a,SKACC_TEMACCOUNTES b,
SKACC_ACCOUNTSES c
where a.temp_dtl=b.temp_dtl
and b.acc_id=c.acc_id
and a.gra_id='5'
/

CREATE OR REPLACE VIEW SKVIEW_APPLICATIONS ("SATU", "LEVELAPP", "APP_NAME", "KOSONG", "APP_CODE", "MODULE_CODE") AS
SELECT -1, LEVEL,  app_name, NULL, a.app_code,module_code FROM
skmst_applications a
CONNECT BY PRIOR a.app_code = module_code
START WITH module_code = 'ONE' 
ORDER BY app_seq,item_seq,app_name
/

CREATE OR REPLACE VIEW SKVIEW_CHECKUPS ("CHECKUP_NO", "CHECKUP_DATE", "REG_NO", "REG_NAME", "SEX", "BIRTH_DATE", "ADDRESS", "CHECKUP_STATUS", "CHECKUP_RJRI") AS
select checkup_no,checkup_date,a.reg_no,reg_name,sex,birth_date,address,checkup_status,status_rjri
from sktxn_checkuphdrs a,skmst_pasiens b
where a.reg_no=b.reg_no
/

CREATE OR REPLACE VIEW SKVIEW_DASHBOARD10RJS ("RJ", "TXN_DATE", "DIAG_ID", "DIAG_DESC", "JML") AS
(
select 'RJ',to_char(sysdate,'yyyymm'),diag_id,diag_desc,jml
  from
(select (a.diag_id),upper(a.diag_desc)diag_desc,count(*)jml
from skmst_mstdiags a,sktxn_rjhdrs c,sktxn_rjdtls d
where c.rj_no=d.rj_no
and a.diag_id=d.diag_id
and rj_status='L'
and to_char(c.rj_date,'yyyymm')=to_char(sysdate,'yyyymm')
group by a.diag_id,diag_desc
order by jml desc)
where rownum <= 10
union
select 'RJ',to_char(add_months(sysdate,-1),'yyyymm'),diag_id,diag_desc,jml
  from
(select (a.diag_id),upper(a.diag_desc)diag_desc,count(*)jml
from skmst_mstdiags a,sktxn_rjhdrs c,sktxn_rjdtls d
where c.rj_no=d.rj_no
and a.diag_id=d.diag_id
and rj_status='L'
and to_char(c.rj_date,'yyyymm')=to_char(add_months(sysdate,-1),'yyyymm')
group by a.diag_id,diag_desc
order by jml desc)
where rownum <= 10
union
select 'RJ',to_char(add_months(sysdate,-2),'yyyymm'),diag_id,diag_desc,jml
  from
(select (a.diag_id),upper(a.diag_desc)diag_desc,count(*)jml
from skmst_mstdiags a,sktxn_rjhdrs c,sktxn_rjdtls d
where c.rj_no=d.rj_no
and a.diag_id=d.diag_id
and rj_status='L'
and to_char(c.rj_date,'yyyymm')=to_char(add_months(sysdate,-2),'yyyymm')
group by a.diag_id,diag_desc
order by jml desc)
where rownum <= 10
union
select 'RJ',to_char(add_months(sysdate,-3),'yyyymm'),diag_id,diag_desc,jml
  from
(select (a.diag_id),upper(a.diag_desc)diag_desc,count(*)jml
from skmst_mstdiags a,sktxn_rjhdrs c,sktxn_rjdtls d
where c.rj_no=d.rj_no
and a.diag_id=d.diag_id
and rj_status='L'
and to_char(c.rj_date,'yyyymm')=to_char(add_months(sysdate,-3),'yyyymm')
group by a.diag_id,diag_desc
order by jml desc)
where rownum <= 10
union
select 'RJ',to_char(add_months(sysdate,-4),'yyyymm'),diag_id,diag_desc,jml
  from
(select (a.diag_id),upper(a.diag_desc)diag_desc,count(*)jml
from skmst_mstdiags a,sktxn_rjhdrs c,sktxn_rjdtls d
where c.rj_no=d.rj_no
and a.diag_id=d.diag_id
and rj_status='L'
and to_char(c.rj_date,'yyyymm')=to_char(add_months(sysdate,-4),'yyyymm')
group by a.diag_id,diag_desc
order by jml desc)
where rownum <= 10
union
select 'RJ',to_char(add_months(sysdate,-5),'yyyymm'),diag_id,diag_desc,jml
  from
(select (a.diag_id),upper(a.diag_desc)diag_desc,count(*)jml
from skmst_mstdiags a,sktxn_rjhdrs c,sktxn_rjdtls d
where c.rj_no=d.rj_no
and a.diag_id=d.diag_id
and rj_status='L'
and to_char(c.rj_date,'yyyymm')=to_char(add_months(sysdate,-5),'yyyymm')
group by a.diag_id,diag_desc
order by jml desc)
where rownum <= 10
union
select 'RJ',to_char(add_months(sysdate,-6),'yyyymm'),diag_id,diag_desc,jml
  from
(select (a.diag_id),upper(a.diag_desc)diag_desc,count(*)jml
from skmst_mstdiags a,sktxn_rjhdrs c,sktxn_rjdtls d
where c.rj_no=d.rj_no
and a.diag_id=d.diag_id
and rj_status='L'
and to_char(c.rj_date,'yyyymm')=to_char(add_months(sysdate,-6),'yyyymm')
group by a.diag_id,diag_desc
order by jml desc)
where rownum <= 10
union
select 'RJ',to_char(add_months(sysdate,-7),'yyyymm'),diag_id,diag_desc,jml
  from
(select (a.diag_id),upper(a.diag_desc)diag_desc,count(*)jml
from skmst_mstdiags a,sktxn_rjhdrs c,sktxn_rjdtls d
where c.rj_no=d.rj_no
and a.diag_id=d.diag_id
and rj_status='L'
and to_char(c.rj_date,'yyyymm')=to_char(add_months(sysdate,-7),'yyyymm')
group by a.diag_id,diag_desc
order by jml desc)
where rownum <= 10
union
select 'RJ',to_char(add_months(sysdate,-8),'yyyymm'),diag_id,diag_desc,jml
  from
(select (a.diag_id),upper(a.diag_desc)diag_desc,count(*)jml
from skmst_mstdiags a,sktxn_rjhdrs c,sktxn_rjdtls d
where c.rj_no=d.rj_no
and a.diag_id=d.diag_id
and rj_status='L'
and to_char(c.rj_date,'yyyymm')=to_char(add_months(sysdate,-8),'yyyymm')
group by a.diag_id,diag_desc
order by jml desc)
where rownum <= 10
union
select 'RJ',to_char(add_months(sysdate,-9),'yyyymm'),diag_id,diag_desc,jml
  from
(select (a.diag_id),upper(a.diag_desc)diag_desc,count(*)jml
from skmst_mstdiags a,sktxn_rjhdrs c,sktxn_rjdtls d
where c.rj_no=d.rj_no
and a.diag_id=d.diag_id
and rj_status='L'
and to_char(c.rj_date,'yyyymm')=to_char(add_months(sysdate,-9),'yyyymm')
group by a.diag_id,diag_desc
order by jml desc)
where rownum <= 10
union
select 'RJ',to_char(add_months(sysdate,-10),'yyyymm'),diag_id,diag_desc,jml
  from
(select (a.diag_id),upper(a.diag_desc)diag_desc,count(*)jml
from skmst_mstdiags a,sktxn_rjhdrs c,sktxn_rjdtls d
where c.rj_no=d.rj_no
and a.diag_id=d.diag_id
and rj_status='L'
and to_char(c.rj_date,'yyyymm')=to_char(add_months(sysdate,-10),'yyyymm')
group by a.diag_id,diag_desc
order by jml desc)
where rownum <= 10
union
select 'RJ',to_char(add_months(sysdate,-11),'yyyymm'),diag_id,diag_desc,jml
  from
(select (a.diag_id),upper(a.diag_desc)diag_desc,count(*)jml
from skmst_mstdiags a,sktxn_rjhdrs c,sktxn_rjdtls d
where c.rj_no=d.rj_no
and a.diag_id=d.diag_id
and rj_status='L'
and to_char(c.rj_date,'yyyymm')=to_char(add_months(sysdate,-11),'yyyymm')
group by a.diag_id,diag_desc
order by jml desc)
where rownum <= 10
)
/

CREATE OR REPLACE VIEW SKVIEW_DASHBOARDKUNJ_C_LAB ("TXN_STATUS", "TXN_DATE", "CLABITEM_DESC", "KUNJUNGAN") AS
select 'RJ',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='RJ'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(sysdate,'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc
union all
select 'UGD',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='UGD'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(sysdate,'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc
union all
select 'RI',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='RI'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(sysdate,'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc
union all
select 'RJ',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='RJ'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-1),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc                                    
union all
select 'UGD',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='UGD'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-1),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc
union all
select 'RI',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='RI'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-1),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc
union all
select 'RJ',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='RJ'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-2),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc                                    
union all
select 'UGD',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='UGD'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-2),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc
union all
select 'RI',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='RI'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-2),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc
union all
select 'RJ',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='RJ'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-3),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc                                    
union all
select 'UGD',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='UGD'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-3),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc
union all
select 'RI',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='RI'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-3),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc
union all
select 'RJ',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='RJ'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-4),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc                                    
union all
select 'UGD',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='UGD'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-4),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc
union all
select 'RI',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='RI'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-4),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc
union all
select 'RJ',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='RJ'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-5),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc                                    
union all
select 'UGD',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='UGD'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-5),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc
union all
select 'RI',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='RI'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-5),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc
union all
select 'RJ',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='RJ'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-6),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc                                    
union all
select 'UGD',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='UGD'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-6),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc
union all
select 'RI',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='RI'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-6),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc
union all
select 'RJ',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='RJ'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-7),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc                                    
union all
select 'UGD',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='UGD'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-7),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc
union all
select 'RI',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='RI'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-7),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc
union all
select 'RJ',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='RJ'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-8),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc                                    
union all
select 'UGD',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='UGD'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-8),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc
union all
select 'RI',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='RI'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-8),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc
union all
select 'RJ',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='RJ'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-9),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc                                    
union all
select 'UGD',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='UGD'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-9),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc
union all
select 'RI',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='RI'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-9),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc
union all
select 'RJ',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='RJ'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-10),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc                                    
union all
select 'UGD',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='UGD'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-10),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc
union all
select 'RI',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='RI'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-10),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc
union all
select 'RJ',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='RJ'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-11),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc                                    
union all
select 'UGD',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='UGD'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-11),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc
union all
select 'RI',to_char(checkup_date,'yyyymm'),clabitem_desc,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,sktxn_checkupdtls b,SKMST_CLABITEMS c
                                    where a.checkup_no=b.checkup_no
                                    and b.clabitem_id=c.clabitem_id
                                    and status_rjri='RI'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-11),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),clabitem_desc
/

CREATE OR REPLACE VIEW SKVIEW_DASHBOARDKUNJ_C_LABX ("TXN_DATE", "CLABITEM_DESC", "KUNJUNGAN") AS
select  TXN_DATE, CLABITEM_DESC,sum(KUNJUNGAN)JUMLAH_PERIKSA
from SKVIEW_DASHBOARDKUNJ_C_LAB
group by TXN_DATE, CLABITEM_DESC
/

CREATE OR REPLACE VIEW SKVIEW_DASHBOARDKUNJ_D_LAB ("TXN_STATUS", "TXN_DATE", "DR_NAME", "KUNJUNGAN") AS
select 'RJ',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,skmst_doctors b
                                    where a.dr_id=b.dr_id
                                    and status_rjri='RJ'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(sysdate,'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name
union all
select 'UGD',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,skmst_doctors b
                                    where status_rjri='UGD'
                                    and a.dr_id=b.dr_id
                                    and to_char(checkup_date,'mm/yyyy')=to_char(sysdate,'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name
union all
select 'RI',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                    from sktxn_checkuphdrs a,skmst_doctors b
                                    where status_rjri='RI'
                                    and a.dr_id=b.dr_id
                                    and to_char(checkup_date,'mm/yyyy')=to_char(sysdate,'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name
union all
select 'RJ',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                     from sktxn_checkuphdrs a,skmst_doctors b
                                    where a.dr_id=b.dr_id
                                    and status_rjri='RJ'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-1),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name                                    
union all
select 'UGD',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                     from sktxn_checkuphdrs a,skmst_doctors b
                                    where a.dr_id=b.dr_id
                                   and status_rjri='UGD'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-1),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name
union all
select 'RI',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                     from sktxn_checkuphdrs a,skmst_doctors b
                                    where a.dr_id=b.dr_id
                                   and status_rjri='RI'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-1),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name
union all
select 'RJ',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                     from sktxn_checkuphdrs a,skmst_doctors b
                                    where a.dr_id=b.dr_id
                                    and status_rjri='RJ'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-2),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name                                    
union all
select 'UGD',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                     from sktxn_checkuphdrs a,skmst_doctors b
                                    where a.dr_id=b.dr_id
                                   and status_rjri='UGD'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-2),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name
union all
select 'RI',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                     from sktxn_checkuphdrs a,skmst_doctors b
                                    where a.dr_id=b.dr_id
                                   and status_rjri='RI'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-2),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name
union all
select 'RJ',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                     from sktxn_checkuphdrs a,skmst_doctors b
                                    where a.dr_id=b.dr_id
                                    and status_rjri='RJ'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-3),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name                                    
union all
select 'UGD',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                     from sktxn_checkuphdrs a,skmst_doctors b
                                    where a.dr_id=b.dr_id
                                   and status_rjri='UGD'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-3),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name
union all
select 'RI',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                     from sktxn_checkuphdrs a,skmst_doctors b
                                    where a.dr_id=b.dr_id
                                   and status_rjri='RI'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-3),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name
union all
select 'RJ',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                     from sktxn_checkuphdrs a,skmst_doctors b
                                    where a.dr_id=b.dr_id
                                    and status_rjri='RJ'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-4),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name                                    
union all
select 'UGD',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                     from sktxn_checkuphdrs a,skmst_doctors b
                                    where a.dr_id=b.dr_id
                                   and status_rjri='UGD'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-4),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name
union all
select 'RI',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                     from sktxn_checkuphdrs a,skmst_doctors b
                                    where a.dr_id=b.dr_id
                                   and status_rjri='RI'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-4),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name
union all
select 'RJ',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                     from sktxn_checkuphdrs a,skmst_doctors b
                                    where a.dr_id=b.dr_id
                                    and status_rjri='RJ'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-5),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name                                    
union all
select 'UGD',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                     from sktxn_checkuphdrs a,skmst_doctors b
                                    where a.dr_id=b.dr_id
                                   and status_rjri='UGD'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-5),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name
union all
select 'RI',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                     from sktxn_checkuphdrs a,skmst_doctors b
                                    where a.dr_id=b.dr_id
                                   and status_rjri='RI'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-5),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name
union all
select 'RJ',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                     from sktxn_checkuphdrs a,skmst_doctors b
                                    where a.dr_id=b.dr_id
                                    and status_rjri='RJ'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-6),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name                                    
union all
select 'UGD',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                     from sktxn_checkuphdrs a,skmst_doctors b
                                    where a.dr_id=b.dr_id
                                   and status_rjri='UGD'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-6),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name
union all
select 'RI',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                     from sktxn_checkuphdrs a,skmst_doctors b
                                    where a.dr_id=b.dr_id
                                   and status_rjri='RI'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-6),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name
union all
select 'RJ',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                     from sktxn_checkuphdrs a,skmst_doctors b
                                    where a.dr_id=b.dr_id
                                    and status_rjri='RJ'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-7),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name                                    
union all
select 'UGD',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                     from sktxn_checkuphdrs a,skmst_doctors b
                                    where a.dr_id=b.dr_id
                                   and status_rjri='UGD'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-7),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name
union all
select 'RI',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                     from sktxn_checkuphdrs a,skmst_doctors b
                                    where a.dr_id=b.dr_id
                                   and status_rjri='RI'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-7),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name
union all
select 'RJ',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                     from sktxn_checkuphdrs a,skmst_doctors b
                                    where a.dr_id=b.dr_id
                                    and status_rjri='RJ'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-8),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name                                    
union all
select 'UGD',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                     from sktxn_checkuphdrs a,skmst_doctors b
                                    where a.dr_id=b.dr_id
                                   and status_rjri='UGD'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-8),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name
union all
select 'RI',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                     from sktxn_checkuphdrs a,skmst_doctors b
                                    where a.dr_id=b.dr_id
                                   and status_rjri='RI'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-8),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name
union all
select 'RJ',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                     from sktxn_checkuphdrs a,skmst_doctors b
                                    where a.dr_id=b.dr_id
                                    and status_rjri='RJ'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-9),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name                                    
union all
select 'UGD',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                     from sktxn_checkuphdrs a,skmst_doctors b
                                    where a.dr_id=b.dr_id
                                   and status_rjri='UGD'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-9),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name
union all
select 'RI',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                     from sktxn_checkuphdrs a,skmst_doctors b
                                    where a.dr_id=b.dr_id
                                   and status_rjri='RI'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-9),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name
union all
select 'RJ',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                     from sktxn_checkuphdrs a,skmst_doctors b
                                    where a.dr_id=b.dr_id
                                    and status_rjri='RJ'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-10),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name                                    
union all
select 'UGD',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                     from sktxn_checkuphdrs a,skmst_doctors b
                                    where a.dr_id=b.dr_id
                                   and status_rjri='UGD'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-10),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name
union all
select 'RI',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                     from sktxn_checkuphdrs a,skmst_doctors b
                                    where a.dr_id=b.dr_id
                                   and status_rjri='RI'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-10),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name
union all
select 'RJ',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                     from sktxn_checkuphdrs a,skmst_doctors b
                                    where a.dr_id=b.dr_id
                                    and status_rjri='RJ'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-11),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name                                    
union all
select 'UGD',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                     from sktxn_checkuphdrs a,skmst_doctors b
                                    where a.dr_id=b.dr_id
                                   and status_rjri='UGD'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-11),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name
union all
select 'RI',to_char(checkup_date,'yyyymm'),dr_name,count(*)jml_kunjungan_lab
                                     from sktxn_checkuphdrs a,skmst_doctors b
                                    where a.dr_id=b.dr_id
                                   and status_rjri='RI'
                                    and to_char(checkup_date,'mm/yyyy')=to_char(add_months(sysdate,-11),'mm/yyyy')
                                    and checkup_status not in('P','F')
group by to_char(checkup_date,'yyyymm'),dr_name
/

CREATE OR REPLACE VIEW SKVIEW_DASHBOARDKUNJ_D_LABX ("TXN_DATE", "DR_NAME", "KUNJUNGAN") AS
select  TXN_DATE, DR_NAME,sum(KUNJUNGAN)JUMLAH_PERIKSA
from SKVIEW_DASHBOARDKUNJ_D_LAB
group by TXN_DATE, DR_NAME
/

CREATE OR REPLACE VIEW SKVIEW_DASHBOARDSLANJUTAN ("PASIENTOTAL", "BULAN", "BULAN1", "BLM_STABIL", "SELESAI_PERAWATAN", "STABIL_RUJUKBALIK", "DIRUJUK_INTERNAL", "DIRUJUK_EXTERNAL") AS
(
select count(*),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-12),'yyyymm')
and status_lanjutan='BS'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-12),'yyyymm')
and status_lanjutan='SP'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-12),'yyyymm')
and status_lanjutan='SR'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-12),'yyyymm')
and status_lanjutan='DI'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-12),'yyyymm')
and status_lanjutan='DE')
from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-12),'yyyymm')
group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
union
select count(*),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-11),'yyyymm')
and status_lanjutan='BS'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-11),'yyyymm')
and status_lanjutan='SP'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-11),'yyyymm')
and status_lanjutan='SR'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-11),'yyyymm')
and status_lanjutan='DI'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-11),'yyyymm')
and status_lanjutan='DE')
from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-11),'yyyymm')
group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
union
select count(*),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-10),'yyyymm')
and status_lanjutan='BS'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-10),'yyyymm')
and status_lanjutan='SP'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-10),'yyyymm')
and status_lanjutan='SR'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-10),'yyyymm')
and status_lanjutan='DI'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-10),'yyyymm')
and status_lanjutan='DE')
from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-10),'yyyymm')
group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
union
select count(*),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-9),'yyyymm')
and status_lanjutan='BS'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-9),'yyyymm')
and status_lanjutan='SP'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-9),'yyyymm')
and status_lanjutan='SR'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-9),'yyyymm')
and status_lanjutan='DI'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-9),'yyyymm')
and status_lanjutan='DE')
from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-9),'yyyymm')
group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
union
select count(*),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-8),'yyyymm')
and status_lanjutan='BS'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-8),'yyyymm')
and status_lanjutan='SP'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-8),'yyyymm')
and status_lanjutan='SR'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-8),'yyyymm')
and status_lanjutan='DI'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-8),'yyyymm')
and status_lanjutan='DE')
from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-8),'yyyymm')
group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
union
select count(*),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-7),'yyyymm')
and status_lanjutan='BS'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-7),'yyyymm')
and status_lanjutan='SP'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-7),'yyyymm')
and status_lanjutan='SR'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-7),'yyyymm')
and status_lanjutan='DI'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-7),'yyyymm')
and status_lanjutan='DE')
from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-7),'yyyymm')
group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
union
select count(*),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-6),'yyyymm')
and status_lanjutan='BS'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-6),'yyyymm')
and status_lanjutan='SP'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-6),'yyyymm')
and status_lanjutan='SR'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-6),'yyyymm')
and status_lanjutan='DI'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-6),'yyyymm')
and status_lanjutan='DE')
from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-6),'yyyymm')
group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
union
select count(*),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-5),'yyyymm')
and status_lanjutan='BS'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-5),'yyyymm')
and status_lanjutan='SP'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-5),'yyyymm')
and status_lanjutan='SR'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-5),'yyyymm')
and status_lanjutan='DI'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-5),'yyyymm')
and status_lanjutan='DE')
from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-5),'yyyymm')
group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
union
select count(*),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-4),'yyyymm')
and status_lanjutan='BS'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-4),'yyyymm')
and status_lanjutan='SP'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-4),'yyyymm')
and status_lanjutan='SR'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-4),'yyyymm')
and status_lanjutan='DI'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-4),'yyyymm')
and status_lanjutan='DE')
from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-4),'yyyymm')
group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
union
select count(*),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-3),'yyyymm')
and status_lanjutan='BS'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-3),'yyyymm')
and status_lanjutan='SP'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-3),'yyyymm')
and status_lanjutan='SR'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-3),'yyyymm')
and status_lanjutan='DI'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-3),'yyyymm')
and status_lanjutan='DE')
from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-3),'yyyymm')
group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
union
select count(*),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-2),'yyyymm')
and status_lanjutan='BS'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-2),'yyyymm')
and status_lanjutan='SP'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-2),'yyyymm')
and status_lanjutan='SR'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-2),'yyyymm')
and status_lanjutan='DI'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-2),'yyyymm')
and status_lanjutan='DE')
from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-2),'yyyymm')
group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
union
select count(*),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-1),'yyyymm')
and status_lanjutan='BS'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-1),'yyyymm')
and status_lanjutan='SP'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-1),'yyyymm')
and status_lanjutan='SR'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-1),'yyyymm')
and status_lanjutan='DI'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-1),'yyyymm')
and status_lanjutan='DE')
from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(add_months(sysdate,-1),'yyyymm')
group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
union
select count(*),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(sysdate,'yyyymm')
and status_lanjutan='BS'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(sysdate,'yyyymm')
and status_lanjutan='SP'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(sysdate,'yyyymm')
and status_lanjutan='SR'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(sysdate,'yyyymm')
and status_lanjutan='DI'),
(select count(*) from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(sysdate,'yyyymm')
and status_lanjutan='DE')
from sktxn_rjhdrs 
where rj_status ='L'
and to_char(rj_date,'yyyymm')=to_Char(sysdate,'yyyymm')
group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
)
/

CREATE OR REPLACE VIEW SKVIEW_DASHBOARD_F20_HTCT ("RJ_NO", "REG_NO", "REG_NAME", "SEX", "RJ_DATE", "DIAG_ID", "TERAPI_OBAT_HTCT") AS
(
select b.rj_no,a.reg_no,reg_name,sex,rj_date,diag_id,(select count(*) from sktxn_rjobats x where x.rj_no=b.rj_no and x.product_id in ('HA00246','HA00247','HA01690','ST01762','CP00115','TR00550','TR01241'))terapi_obat_HTCT
from skmst_pasiens a,sktxn_rjhdrs b,sktxn_rjdtls c
where a.reg_no=b.reg_no
and b.rj_no=c.rj_no
and diag_id='F20')
/

CREATE OR REPLACE VIEW SKVIEW_DASHBOARD_F20_JML ("JML", "BULAN", "BULAN1", "STATUS_F20") AS
(
select count (RJ_NO),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),'HTCT' from SKVIEW_DASHBOARD_F20_HTCT where TERAPI_OBAT_HTCT>0 and to_char(rj_date,'mm/yyyy')=to_char(add_months(sysdate,-1),'mm/yyyy') group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
union
select count (RJ_NO),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),'NON' from SKVIEW_DASHBOARD_F20_HTCT where TERAPI_OBAT_HTCT<1 and to_char(rj_date,'mm/yyyy')=to_char(add_months(sysdate,-1),'mm/yyyy') group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm') 
union
select count (RJ_NO),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),'HTCT' from SKVIEW_DASHBOARD_F20_HTCT where TERAPI_OBAT_HTCT>0 and to_char(rj_date,'mm/yyyy')=to_char(add_months(sysdate,-2),'mm/yyyy') group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
union
select count (RJ_NO),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),'NON' from SKVIEW_DASHBOARD_F20_HTCT where TERAPI_OBAT_HTCT<1 and to_char(rj_date,'mm/yyyy')=to_char(add_months(sysdate,-2),'mm/yyyy') group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
union
select count (RJ_NO),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),'HTCT' from SKVIEW_DASHBOARD_F20_HTCT where TERAPI_OBAT_HTCT>0 and to_char(rj_date,'mm/yyyy')=to_char(add_months(sysdate,-3),'mm/yyyy') group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
union
select count (RJ_NO),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),'NON' from SKVIEW_DASHBOARD_F20_HTCT where TERAPI_OBAT_HTCT<1 and to_char(rj_date,'mm/yyyy')=to_char(add_months(sysdate,-3),'mm/yyyy') group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
union
select count (RJ_NO),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),'HTCT' from SKVIEW_DASHBOARD_F20_HTCT where TERAPI_OBAT_HTCT>0 and to_char(rj_date,'mm/yyyy')=to_char(add_months(sysdate,-4),'mm/yyyy') group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
union
select count (RJ_NO),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),'NON' from SKVIEW_DASHBOARD_F20_HTCT where TERAPI_OBAT_HTCT<1 and to_char(rj_date,'mm/yyyy')=to_char(add_months(sysdate,-4),'mm/yyyy') group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
union
select count (RJ_NO),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),'HTCT' from SKVIEW_DASHBOARD_F20_HTCT where TERAPI_OBAT_HTCT>0 and to_char(rj_date,'mm/yyyy')=to_char(add_months(sysdate,-5),'mm/yyyy') group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
union
select count (RJ_NO),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),'NON' from SKVIEW_DASHBOARD_F20_HTCT where TERAPI_OBAT_HTCT<1 and to_char(rj_date,'mm/yyyy')=to_char(add_months(sysdate,-5),'mm/yyyy') group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
union
select count (RJ_NO),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),'HTCT' from SKVIEW_DASHBOARD_F20_HTCT where TERAPI_OBAT_HTCT>0 and to_char(rj_date,'mm/yyyy')=to_char(add_months(sysdate,-6),'mm/yyyy') group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
union
select count (RJ_NO),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),'NON' from SKVIEW_DASHBOARD_F20_HTCT where TERAPI_OBAT_HTCT<1 and to_char(rj_date,'mm/yyyy')=to_char(add_months(sysdate,-6),'mm/yyyy') group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
union
select count (RJ_NO),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),'HTCT' from SKVIEW_DASHBOARD_F20_HTCT where TERAPI_OBAT_HTCT>0 and to_char(rj_date,'mm/yyyy')=to_char(add_months(sysdate,-7),'mm/yyyy') group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
union
select count (RJ_NO),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),'NON' from SKVIEW_DASHBOARD_F20_HTCT where TERAPI_OBAT_HTCT<1 and to_char(rj_date,'mm/yyyy')=to_char(add_months(sysdate,-7),'mm/yyyy') group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
union
select count (RJ_NO),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),'HTCT' from SKVIEW_DASHBOARD_F20_HTCT where TERAPI_OBAT_HTCT>0 and to_char(rj_date,'mm/yyyy')=to_char(add_months(sysdate,-8),'mm/yyyy') group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
union
select count (RJ_NO),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),'NON' from SKVIEW_DASHBOARD_F20_HTCT where TERAPI_OBAT_HTCT<1 and to_char(rj_date,'mm/yyyy')=to_char(add_months(sysdate,-8),'mm/yyyy') group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
union
select count (RJ_NO),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),'HTCT' from SKVIEW_DASHBOARD_F20_HTCT where TERAPI_OBAT_HTCT>0 and to_char(rj_date,'mm/yyyy')=to_char(add_months(sysdate,-9),'mm/yyyy') group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
union
select count (RJ_NO),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),'NON' from SKVIEW_DASHBOARD_F20_HTCT where TERAPI_OBAT_HTCT<1 and to_char(rj_date,'mm/yyyy')=to_char(add_months(sysdate,-9),'mm/yyyy') group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
union
select count (RJ_NO),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),'HTCT' from SKVIEW_DASHBOARD_F20_HTCT where TERAPI_OBAT_HTCT>0 and to_char(rj_date,'mm/yyyy')=to_char(add_months(sysdate,-10),'mm/yyyy') group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
union
select count (RJ_NO),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),'NON' from SKVIEW_DASHBOARD_F20_HTCT where TERAPI_OBAT_HTCT<1 and to_char(rj_date,'mm/yyyy')=to_char(add_months(sysdate,-10),'mm/yyyy') group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
union
select count (RJ_NO),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),'HTCT' from SKVIEW_DASHBOARD_F20_HTCT where TERAPI_OBAT_HTCT>0 and to_char(rj_date,'mm/yyyy')=to_char(add_months(sysdate,-11),'mm/yyyy') group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
union
select count (RJ_NO),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),'NON' from SKVIEW_DASHBOARD_F20_HTCT where TERAPI_OBAT_HTCT<1 and to_char(rj_date,'mm/yyyy')=to_char(add_months(sysdate,-11),'mm/yyyy') group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
union
select count (RJ_NO),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),'HTCT' from SKVIEW_DASHBOARD_F20_HTCT where TERAPI_OBAT_HTCT>0 and to_char(rj_date,'mm/yyyy')=to_char(add_months(sysdate,-12),'mm/yyyy') group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')
union
select count (RJ_NO),to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm'),'NON' from SKVIEW_DASHBOARD_F20_HTCT where TERAPI_OBAT_HTCT<1 and to_char(rj_date,'mm/yyyy')=to_char(add_months(sysdate,-12),'mm/yyyy') group by to_char(rj_date,'mm/yyyy'),to_char(rj_date,'yyyymm')

)
/

CREATE OR REPLACE VIEW SKVIEW_DB_RJS ("RJ", "TXN_DATE", "NAMA_KECAMATAN", "JML_KUNJUNGAN") AS
(
select 'RJ',to_char(rj_date,'yyyymm'),kec_name,count(*)
from sktxn_RJhdrs a,skmst_pasiens b,skmst_desas c,skmst_kecamatans d
where a.reg_no=b.reg_no
and b.des_id=c.des_id
and c.kec_id=d.kec_id
and RJ_status ='L'
and to_char(rj_date,'yyyymm') = to_char(sysdate,'yyyymm')
group by 'RJ',to_char(rj_date,'yyyymm'),kec_name
union
select 'RJ',to_char(add_months(sysdate,-1),'yyyymm'),kec_name,count(*)
from sktxn_RJhdrs a,skmst_pasiens b,skmst_desas c,skmst_kecamatans d
where a.reg_no=b.reg_no
and b.des_id=c.des_id
and c.kec_id=d.kec_id
and RJ_status ='L'
and to_char(rj_date,'yyyymm') = to_char(add_months(sysdate,-1),'yyyymm')
group by 'RJ',to_char(add_months(sysdate,-1),'yyyymm'),kec_name
union
select 'RJ',to_char(add_months(sysdate,-2),'yyyymm'),kec_name,count(*)
from sktxn_RJhdrs a,skmst_pasiens b,skmst_desas c,skmst_kecamatans d
where a.reg_no=b.reg_no
and b.des_id=c.des_id
and c.kec_id=d.kec_id
and RJ_status ='L'
and to_char(rj_date,'yyyymm') = to_char(add_months(sysdate,-2),'yyyymm')
group by 'RJ',to_char(add_months(sysdate,-2),'yyyymm'),kec_name
union
select 'RJ',to_char(add_months(sysdate,-3),'yyyymm'),kec_name,count(*)
from sktxn_RJhdrs a,skmst_pasiens b,skmst_desas c,skmst_kecamatans d
where a.reg_no=b.reg_no
and b.des_id=c.des_id
and c.kec_id=d.kec_id
and RJ_status ='L'
and to_char(rj_date,'yyyymm') = to_char(add_months(sysdate,-3),'yyyymm')
group by 'RJ',to_char(add_months(sysdate,-3),'yyyymm'),kec_name
union
select 'RJ',to_char(add_months(sysdate,-4),'yyyymm'),kec_name,count(*)
from sktxn_RJhdrs a,skmst_pasiens b,skmst_desas c,skmst_kecamatans d
where a.reg_no=b.reg_no
and b.des_id=c.des_id
and c.kec_id=d.kec_id
and RJ_status ='L'
and to_char(rj_date,'yyyymm') = to_char(add_months(sysdate,-4),'yyyymm')
group by 'RJ',to_char(add_months(sysdate,-4),'yyyymm'),kec_name
union
select 'RJ',to_char(add_months(sysdate,-5),'yyyymm'),kec_name,count(*)
from sktxn_RJhdrs a,skmst_pasiens b,skmst_desas c,skmst_kecamatans d
where a.reg_no=b.reg_no
and b.des_id=c.des_id
and c.kec_id=d.kec_id
and RJ_status ='L'
and to_char(rj_date,'yyyymm') = to_char(add_months(sysdate,-5),'yyyymm')
group by 'RJ',to_char(add_months(sysdate,-5),'yyyymm'),kec_name
union
select 'RJ',to_char(add_months(sysdate,-6),'yyyymm'),kec_name,count(*)
from sktxn_RJhdrs a,skmst_pasiens b,skmst_desas c,skmst_kecamatans d
where a.reg_no=b.reg_no
and b.des_id=c.des_id
and c.kec_id=d.kec_id
and RJ_status ='L'
and to_char(rj_date,'yyyymm') = to_char(add_months(sysdate,-6),'yyyymm')
group by 'RJ',to_char(add_months(sysdate,-6),'yyyymm'),kec_name
union
select 'RJ',to_char(add_months(sysdate,-7),'yyyymm'),kec_name,count(*)
from sktxn_RJhdrs a,skmst_pasiens b,skmst_desas c,skmst_kecamatans d
where a.reg_no=b.reg_no
and b.des_id=c.des_id
and c.kec_id=d.kec_id
and RJ_status ='L'
and to_char(rj_date,'yyyymm') = to_char(add_months(sysdate,-7),'yyyymm')
group by 'RJ',to_char(add_months(sysdate,-7),'yyyymm'),kec_name
union
select 'RJ',to_char(add_months(sysdate,-8),'yyyymm'),kec_name,count(*)
from sktxn_RJhdrs a,skmst_pasiens b,skmst_desas c,skmst_kecamatans d
where a.reg_no=b.reg_no
and b.des_id=c.des_id
and c.kec_id=d.kec_id
and RJ_status ='L'
and to_char(rj_date,'yyyymm') = to_char(add_months(sysdate,-8),'yyyymm')
group by 'RJ',to_char(add_months(sysdate,-8),'yyyymm'),kec_name
union
select 'RJ',to_char(add_months(sysdate,-9),'yyyymm'),kec_name,count(*)
from sktxn_RJhdrs a,skmst_pasiens b,skmst_desas c,skmst_kecamatans d
where a.reg_no=b.reg_no
and b.des_id=c.des_id
and c.kec_id=d.kec_id
and RJ_status ='L'
and to_char(rj_date,'yyyymm') = to_char(add_months(sysdate,-9),'yyyymm')
group by 'RJ',to_char(add_months(sysdate,-9),'yyyymm'),kec_name
union
select 'RJ',to_char(add_months(sysdate,-10),'yyyymm'),kec_name,count(*)
from sktxn_RJhdrs a,skmst_pasiens b,skmst_desas c,skmst_kecamatans d
where a.reg_no=b.reg_no
and b.des_id=c.des_id
and c.kec_id=d.kec_id
and RJ_status ='L'
and to_char(rj_date,'yyyymm') = to_char(add_months(sysdate,-10),'yyyymm')
group by 'RJ',to_char(add_months(sysdate,-10),'yyyymm'),kec_name
union
select 'RJ',to_char(add_months(sysdate,-11),'yyyymm'),kec_name,count(*)
from sktxn_RJhdrs a,skmst_pasiens b,skmst_desas c,skmst_kecamatans d
where a.reg_no=b.reg_no
and b.des_id=c.des_id
and c.kec_id=d.kec_id
and RJ_status ='L'
and to_char(rj_date,'yyyymm') = to_char(add_months(sysdate,-11),'yyyymm')
group by 'RJ',to_char(add_months(sysdate,-11),'yyyymm'),kec_name
)
/

CREATE OR REPLACE VIEW SKVIEW_DRRJANTRIAN ("DR_ID", "DR_NAME", "TXN_DATE", "POLI_ID", "POLI_DESC") AS
select a.dr_id,dr_name,to_char(rj_date,'dd/mm/yyyy'),a.poli_id,poli_desc
from sktxn_rjhdrs a,skmst_doctors b,skmst_polis c
where a.dr_id=b.dr_id
and a.poli_id=c.poli_id
and rj_status!='F'
group by a.dr_id,dr_name,to_char(rj_date,'dd/mm/yyyy'),a.poli_id,poli_desc
/

CREATE OR REPLACE VIEW SKVIEW_ERMSTATUS ("LAYANAN_STATUS", "TXN_NO", "REG_NO", "REG_NAME", "TXN_DATE", "ERM_STATUS", "POLI", "KD_POLI_BPJS", "KD_DR_BPJS", "NOKARTU_BPJS") AS
(
select 'RJ',rj_no,reg_no,reg_name,rj_date,erm_status,dr_name||' / '||poli_desc,kd_poli_bpjs,kd_dr_bpjs,(select nokartu_bpjs from skmst_pasiens x where x.reg_no=a.reg_no)nokartu_bpjs from SKVIEW_RJKASIR a where rj_status not in ('F')

)
/

CREATE OR REPLACE VIEW SKVIEW_HPPES ("HPP_YEAR", "GRA_ID", "DK_STATUS", "ACC_ID", "ACC_DESC", "HPP") AS
select sa_year,c.gra_id,dk_status,a.acc_id,acc_desc,

        (select nvl(sa_acc_d,0)-nvl(sa_acc_k,0)saldo_awal 
        from SKTXN_SALDOAWALAKUNS 
        where acc_id in(select acc_id from SKACC_CONFACCTXNS where conf_id='2')
		and sa_year=d.sa_year)+
        
        (select (sum(txn_d)-sum(txn_k) )persediaan
		from skview_accounts a,skacc_accountses b,skacc_gr_accountses c
		where a.txn_acc=b.acc_id
		and b.gra_id=c.gra_id
		and acc_id in(select acc_id from SKACC_CONFACCTXNS where conf_id='2') 
		and to_number(txn_d||txn_k)>0
		and to_char(txn_date,'yyyy')=d.sa_year)-
        
        sum(nvl(HPP_PRODUCT,0)*nvl(STOCKWH_AKHIR,0)) HPP

from SKACC_CONFACCTXNS a, skacc_accountses b,skacc_gr_accountses c,SKVIEW_SALDOAKHIRSTOCKS d
where a.acc_id=b.acc_id
and b.gra_id=c.gra_id
and conf_id='9'
group by sa_year,c.gra_id,dk_status,a.acc_id,acc_desc
/

CREATE OR REPLACE VIEW SKVIEW_IOHUTANGS ("SUPP_ID", "TXN_STATUS", "TXN_NO", "TXN_DATE", "SUPP_NAME", "IOH_D", "IOH_K") AS
(
select supp_id,'RCV',rcv_no,rcv_date,supp_name,nvl(totalall,0),0
from SKVIEW_RCVHDRS a
where rcv_status not in ('A','F')
union
select a.supp_id,'CO',a.cashout_no,cashout_date,supp_name,0,cashout_value
from SKTXN_CASHOUTHDRS a,SKTXN_CASHOUTdtls b,skmst_suppliers c
where a.cashout_no=b.cashout_no
and a.supp_id=c.supp_id
and rcv_no in (select rcv_no from sktxn_rcvhdrs where rcv_status not in ('A','F'))
)
/

CREATE OR REPLACE VIEW SKVIEW_IOPIUTANGS ("CM_ID", "TXN_STATUS", "TXN_NO", "TXN_DATE", "CM_NAME", "IOP_D", "IOP_K") AS
(select b.cm_id,'SLS',b.sls_no,sls_date,cm_name,sum(/**/(nvl(qty,0)*nvl(sales_price,0))/**/-/**/((nvl(qty,0)*nvl(sales_price,0))*nvl(dtl_persen,0)/100)/**/-/**/nvl(dtl_diskon,0)/**/)-nvl(b.sls_diskon,0) total,0
from sktxn_slsdtls a,sktxn_slshdrs b,skmst_customers c
where a.sls_no=b.sls_no
and b.cm_id=c.cm_id
and sls_status not in ('A','F')
group by b.cm_id,cm_name,b.sls_no,b.sls_diskon,sls_date
union
select a.cm_id,'CI',a.cashin_no,cashin_date,cm_name,0,cashin_value
from SKTXN_CASHINHDRS a,sktxn_cashindtls b,skmst_customers c
where a.cashin_no=b.cashin_no
and a.cm_id=c.cm_id
and sls_no in (select sls_no from sktxn_slshdrs where sls_status not in ('A','F'))
)
/

CREATE OR REPLACE VIEW SKVIEW_IOSTOCKWHS ("PRODUCT_ID", "TXN_STATUS", "QTY_D", "QTY_K", "TXN_DATE", "TXN_NO", "PRODUCT_NAME") AS
(
    SELECT b.product_id, 'RCV', SUM(qty), 0, rcv_date, a.rcv_no, product_name
    FROM SKTXN_RCVHDRS a, SKTXN_RCVDTLS b, SKMST_PRODUCTS c
    WHERE a.rcv_no = b.rcv_no
        AND rcv_status NOT IN ('A','F')
        AND b.product_id = c.product_id
    GROUP BY b.product_id, 'RCV', 0, rcv_date, a.rcv_no, product_name
    UNION ALL
    SELECT b.product_id, 'SLS', 0, SUM(qty), sls_date, a.sls_no, product_name
    FROM SKTXN_SLSDTLS b, SKTXN_SLSHDRS a, SKMST_PRODUCTS c
    WHERE a.sls_no = b.sls_no
        AND b.product_id = c.product_id
        AND sls_status NOT IN ('A','F')
    GROUP BY b.product_id, 'SLS', 0, sls_date, a.sls_no, product_name
    UNION ALL
    SELECT b.product_id, 'RJ', 0, SUM(qty), rj_date, a.rj_no, product_name
    FROM SKTXN_RJOBATS b, SKTXN_RJHDRS a, SKMST_PRODUCTS c
    WHERE a.rj_no = b.rj_no
        AND b.product_id = c.product_id
        AND rj_status NOT IN ('A','F')
    GROUP BY b.product_id, 'RJ', 0, rj_date, a.rj_no, product_name
    UNION ALL
    SELECT s.product_id, 'SO', NVL(s.so_d, 0), NVL(s.so_k, 0),
           s.so_date, s.so_no, c.product_name
    FROM SKTXN_SOWHS s, SKMST_PRODUCTS c
    WHERE s.product_id = c.product_id
)
/

CREATE OR REPLACE VIEW SKVIEW_LASTRCV ("RCV_DATE", "RCV_NO", "PRODUCT_ID") AS
select rcv_date,a.rcv_no, product_id
from sktxn_rcvhdrs a,sktxn_rcvdtls b
where a.rcv_no=b.rcv_no
and rcv_status not in ('A','F')
and rcv_date= 
                (select max(rcv_date) 
                from sktxn_rcvhdrs z,sktxn_rcvdtls y
                where z.rcv_no=y.rcv_no
                and rcv_status not in ('A','F')
                and y.product_id=b.product_id)
/

CREATE OR REPLACE VIEW SKVIEW_RADS ("RAD_DATE", "TXN_NO", "TXN_NO_DTL", "REG_NO", "REG_NAME", "SEX", "BIRTH_DATE", "ADDRESS", "RAD_UPLOAD_PDF", "RAD_RJRI", "RAD_ID", "RAD_DESC") AS
(

select rj_date,
                rj_no,
                rad_dtl,
                reg_no,
                reg_name,
                sex,
                birth_date,
                address,
                RAD_UPLOAD_PDF,
                'RJ',
                rad_id,
                rad_desc
                from SKVIEW_RJRADS

                )
/

CREATE OR REPLACE VIEW SKVIEW_RCVDTLS ("RCV_NO", "RCV_STATUS", "RCV_DATE", "SUPP_ID", "SUPP_NAME", "PRODUCT_ID", "PRODUCT_NAME", "COST_PRICE") AS
SELECT A.RCV_no,RCV_status,RCV_date,A.SUPP_id,SUPP_name,b.product_id,product_name,b.cost_price
FROM SKTXN_RCVHDRS A,SKTXN_RCVDTLS b, SKMST_SUPPLIERS c,skmst_products d
WHERE a.rcv_no=b.rcv_no
and A.supp_id=c.supp_id
and b.product_id=d.product_id
/

CREATE OR REPLACE VIEW SKVIEW_RCVHDRS ("RCV_NO", "RCV_STATUS", "RCV_DATE", "DUE_DATE", "SUPP_ID", "SUPP_NAME", "RCV_DESC", "CHECK_BOXSTATUS", "VCOUNT", "TOTALALL", "TOTALPPN") AS
SELECT A.RCV_no,RCV_status,RCV_date,DUE_DATE,A.SUPP_id,SUPP_name,RCV_desc,check_boxstatus, VCOUNT,
/*cari total-diskon*/
(select sum(
/*persen1*/(nvl(qty,0)*nvl(cost_price,0))/**/-/**/((nvl(qty,0)*nvl(cost_price,0))*nvl(dtl_persen,0)/100)/**/-/**/nvl(dtl_diskon,0)-
/*persen2*/(((nvl(qty,0)*nvl(cost_price,0))/**/-/**/((nvl(qty,0)*nvl(cost_price,0))*nvl(dtl_persen,0)/100)/**/-/**/nvl(dtl_diskon,0))*
(nvl(dtl_persen1,0)/100))-/**/nvl(dtl_diskon1,0))
from sktxn_rcvdtls z
where z.rcv_no=a.rcv_no)-nvl(a.RCV_diskon,0)
/*biaya materai*/
+nvl(rcv_materai,0)
/*ppn*/
/*cari ppn*/
+(  (  (select sum(
/*persen1*/(nvl(qty,0)*nvl(cost_price,0))/**/-/**/((nvl(qty,0)*nvl(cost_price,0))*nvl(dtl_persen,0)/100)/**/-/**/nvl(dtl_diskon,0)-
/*persen2*/(((nvl(qty,0)*nvl(cost_price,0))/**/-/**/((nvl(qty,0)*nvl(cost_price,0))*nvl(dtl_persen,0)/100)/**/-/**/nvl(dtl_diskon,0))*
(nvl(dtl_persen1,0)/100))-/**/nvl(dtl_diskon1,0))
from sktxn_rcvdtls z
where z.rcv_no=a.rcv_no)-nvl(a.RCV_diskon,0)  )*nvl(rcv_ppn,0)/100)totalall,
(  (  (select sum(
/*persen1*/(nvl(qty,0)*nvl(cost_price,0))/**/-/**/((nvl(qty,0)*nvl(cost_price,0))*nvl(dtl_persen,0)/100)/**/-/**/nvl(dtl_diskon,0)-
/*persen2*/(((nvl(qty,0)*nvl(cost_price,0))/**/-/**/((nvl(qty,0)*nvl(cost_price,0))*nvl(dtl_persen,0)/100)/**/-/**/nvl(dtl_diskon,0))*
(nvl(dtl_persen1,0)/100))-/**/nvl(dtl_diskon1,0))
from sktxn_rcvdtls z
where z.rcv_no=a.rcv_no)-nvl(a.RCV_diskon,0)  )*nvl(rcv_ppn,0)/100)totalppn
FROM SKTXN_RCVHDRS A,SKMST_SUPPLIERS b
WHERE A.supp_id=b.supp_id
/

CREATE OR REPLACE VIEW SKVIEW_RCVPAYS ("RCV_STATUS", "SUPP_ID", "VCOUNT", "RCV_NO", "TOTAL_BAYAR_MIN_TITIP") AS
select a.RCV_STATUS, a.SUPP_ID, a.VCOUNT, a.RCV_NO, nvl(totalall,0)-(select nvl(sum(rcvp_value),0) from sktxn_rcvpayments where rcv_no in (select rcv_no from sktxn_rcvhdrs where rcv_no=a.rcv_no)) total
from SKVIEW_RCVHDRS a
order by vcount,a.rcv_no
/

CREATE OR REPLACE VIEW SKVIEW_RJKASIR ("REG_NO", "REG_NAME", "SEX", "ADDRESS", "THN", "BLN", "HR", "RJ_NO", "RJ_STATUS", "RJ_DISKON", "RJ_TOTAL", "DR_ID", "DR_NAME", "POLI_ID", "POLI_DESC", "BIRTH_DATE", "RJ_DATE", "SHIFT", "RJ_DIAGNOSA", "KLAIM_ID", "TXN_STATUS", "CEK_BAYAR", "VNO_SEP", "NO_ANTRIAN", "WAKTU_MASUK_POLI", "WAKTU_MASUK_APT", "WAKTU_MASUK_PELAYANAN", "WAKTU_SELESAI_PELAYANAN", "NOBOOKING", "ERM_STATUS", "DATADAFTARPOLIRJ_JSON", "KD_DR_BPJS", "KD_POLI_BPJS", "DR_UUID", "POLI_UUID", "PATIENT_UUID", "PASS_STATUS") AS
SELECT a.reg_no, a.reg_name, a.sex, a.address,
       TRUNC (MONTHS_BETWEEN (SYSDATE, birth_date) / 12),
       MOD (TRUNC (MONTHS_BETWEEN (SYSDATE, birth_date)), 12), 0, b.rj_no,
       b.rj_status, b.rj_diskon, b.rj_total, b.dr_id,dr_name, b.poli_id,poli_desc, a.birth_date,
       rj_date, shift,(select count(*) from SKTXN_RJDTLS where rj_no=b.rj_no),klaim_id,txn_status,cek_bayar,vno_sep,no_antrian, 
       WAKTU_MASUK_POLI, 
 WAKTU_MASUK_APT, WAKTU_MASUK_PELAYANAN, WAKTU_SELESAI_PELAYANAN, NOBOOKING,nvl(ERM_STATUS,'A'),
 DATADAFTARPOLIRJ_JSON,kd_dr_bpjs,kd_poli_bpjs,DR_UUID,POLI_UUID,PATIENT_UUID,PASS_STATUS
  FROM SKMST_PASIENS a, SKTXN_RJHDRS b,skmst_polis c,skmst_doctors d
 WHERE a.reg_no = b.reg_no
 and b.poli_id=c.poli_id
 and b.dr_id=d.dr_id
/

CREATE OR REPLACE VIEW SKVIEW_RJRADS ("REG_NO", "REG_NAME", "SEX", "ADDRESS", "THN", "BLN", "HR", "RJ_NO", "RJ_STATUS", "RJ_DISKON", "RJ_TOTAL", "DR_ID", "POLI_ID", "BIRTH_DATE", "RJ_DATE", "SHIFT", "RJ_DIAGNOSA", "KLAIM_ID", "TXN_STATUS", "CEK_BAYAR", "RAD_ID", "RAD_DESC", "RAD_RESULT", "RAD_DTL", "RAD_PRICE", "RAD_JD", "RAD_JM", "KRITIS_STATUS", "RAD_UPLOAD_PDF") AS
SELECT a.reg_no, reg_name, sex, address,
       TRUNC (MONTHS_BETWEEN (SYSDATE, birth_date) / 12),
       MOD (TRUNC (MONTHS_BETWEEN (SYSDATE, birth_date)), 12), 0, a.rj_no,
       rj_status, rj_diskon, rj_total, dr_id, poli_id, birth_date,
       rj_date, shift,rj_diagnosa,klaim_id,txn_status,cek_bayar,b.rad_id,rad_desc,rad_result,rad_dtl,b.rad_price,rad_jd,rad_jm,nvl(kritis_status,'0'),rad_upload_pdf
  FROM SKTXN_RJHDRS a,sktxn_rjrads b,SKMST_PASIENS c,skmst_radiologis d
 WHERE a.rj_no=b.rj_no 
 and a.reg_no = c.reg_no
 and b.rad_id=d.rad_id
/

CREATE OR REPLACE VIEW SKVIEW_RJREGS ("REG_NO", "REG_NAME", "RJ_NO") AS
(
select a.reg_no,a.reg_name,b.rj_no from skmst_pasiens a, sktxn_rjhdrs b where a.reg_no = b.reg_no)
/

CREATE OR REPLACE VIEW SKVIEW_RJSTRS ("TXN_ID", "TXN_DESC", "TXN_NOMINAL", "RJ_NO", "TXN_NO") AS
(
select 'ADMIN RAWAT JALAN','ADMIN KLINIK',rs_admin,rj_no,1 from sktxn_rjhdrs
union all
select 'ADMIN RAWAT JALAN','ADMIN RAWAT JALAN',rj_admin,rj_no,1 from sktxn_rjhdrs
union all
select 'ADMIN UP','UANG PERIKSA POLI',poli_price,rj_no,2 from sktxn_rjhdrs
union all
select 'JASA DOKTER',dr_name||'  '||accdoc_desc||'  '||count(*)||' (X)',sum(b.accdoc_price)accdoc_price,a.rj_no,3
from sktxn_rjhdrs a,sktxn_rjaccdocs b,skmst_accdocs c,skmst_doctors d
where a.rj_no=b.rj_no
and b.accdoc_id=c.accdoc_id
and a.dr_id=d.dr_id
group by dr_name,accdoc_desc,a.rj_no
union all
select 'JASA MEDIS',pact_desc||'  '||count(*)||' (X)',sum(b.PACT_PRICE)PACT_PRICE,a.rj_no,4
from sktxn_rjhdrs a,SKTXN_RJACTPARAMS b,skmst_actparamedics c 
where a.rj_no=b.rj_no
and b.pact_id=c.pact_id
group by  pact_desc,a.rj_no
union all
select 'JASA KARYAWAN','JASA KARYAWAN',sum(acte_price),rj_no,5
from sktxn_rjactemps 
group by rj_no
union all
select 'RADIOLOGI',rad_desc||'  '||count(*)||' (X)',sum(a.rad_price)rad_price,rj_no,6
from sktxn_rjrads a,skmst_radiologis b
where a.rad_id=b.rad_id
group by rj_no,rad_desc
union all
select 'LABORAT',lab_Desc,sum(lab_price)lab_price,rj_no,7
from sktxn_rjlabs
group by lab_desc,rj_no
union all
select 'OBAT','BIAYA OBAT RAWAT JALAN',sum(nvl(qty,0)*nvl(price,0)),rj_no,8
from SKTXN_RJOBATS 
group by rj_no
union all
select 'LAIN-LAIN',other_desc||'  '||count(*)||' (X)',SUM(a.other_price),rj_no,9
from sktxn_rjothers a,skmst_others b
where a.other_id=b.other_id
GROUP BY other_desc,rj_no
)
/

CREATE OR REPLACE VIEW SKVIEW_RJUPLOADBPJSES ("JENIS_FILE", "UPLOADBPJS", "RJ_NO", "SEQ_FILE") AS
select jenis_file,uploadbpjs,rj_no,seq_file from SKTXN_RJUPLOADBPJSES order by rj_no,seq_file,uploadbpjs desc
/

CREATE OR REPLACE VIEW SKVIEW_RSLABS ("CHECKUP_NO", "CHECKUP_DATE", "REG_NO", "REG_NAME", "SHIFT", "STATUS_RJRI") AS
(
select a.checkup_no,checkup_date,a.reg_no,reg_name,('2')shift,STATUS_RJRI from SKTXN_CHECKUPHDRS a,skmst_pasiens b
where a.reg_no=b.reg_no
and checkup_status!='F')
/

CREATE OR REPLACE VIEW SKVIEW_SALDOAKHIRSTOCKS ("SA_YEAR", "PRODUCT_ID", "HPP_PRODUCT", "STOCKWH_AKHIR") AS
(select a.sa_year,a.product_id,hpp_product,sum(         (select sum(sa_stockwh) 
                                                        from SKTXN_SALDOAWALSTOCKS x
                                                        where x.sa_year=a.sa_year
                                                        and x.product_id=a.product_id)+
                                                                                      (select nvl(sum(qty_d),0)-nvl(sum(qty_k),0)gudangnow
                                                                                       from SKVIEW_IOSTOCKWHS y
                                                                                       where to_char(txn_date,'yyyy')=a.sa_year
                                                                                       and y.product_id=a.product_id)
                                                )saldo_akhir
		from SKTXN_SALDOAWALSTOCKS a 
        group by a.sa_year,a.product_id,hpp_product
)
/

CREATE OR REPLACE VIEW SKVIEW_SALDOAWALHUTANG ("SUPP_ID", "SUPP_NAME", "SAH_VALUE", "SAH_YEAR") AS
(SELECT a.supp_id,supp_name, a.SAh_VALUE,SAh_year 
FROM SKTXN_SALDOAWALhutangS a,SKMST_supplierS b
WHERE a.supp_id=b.supp_id)
/

CREATE OR REPLACE VIEW SKVIEW_SALDOAWALPIUTANG ("CM_ID", "CM_NAME", "SAP_VALUE", "SAP_YEAR") AS
(SELECT a.cm_id,cm_name, a.SAP_VALUE,SAP_year 
FROM SKTXN_SALDOAWALPIUTANGS a,SKMST_CUSTOMERS b
WHERE a.cm_id=b.cm_id)
/

CREATE OR REPLACE VIEW SKVIEW_SALDOAWALSTOCKS ("PRODUCT_ID", "PRODUCT_NAME", "SA_STOCKWH", "SA_YEAR", "ACTIVE_STATUS", "COST_PRICE", "SALES_PRICE") AS
(SELECT a.product_id,b.product_name,a.sa_stockwh,sa_year,b.active_status,cost_price,sales_price
FROM SKTXN_SALDOAWALSTOCKS a,SKMST_PRODUCTS b
WHERE a.product_id=b.product_id)
/

CREATE OR REPLACE VIEW SKVIEW_SALDOSTOCK_LIST ("PRODUCT_ID", "PRODUCT_NAME", "COST_PRICE", "SALES_PRICE", "LIMIT_STOCK", "ACTIVE_STATUS", "SALDO_AKHIR", "COST_PRICE_AFTER_DISC") AS
select c.product_id,('  '||product_name)product_name,cost_price,sales_price,limit_stock,active_status,
(  (SELECT nvl(sa_stockwh,0) 
from SKVIEW_SALDOAWALSTOCKS 
where product_id=c.product_id
and sa_year=TO_CHAR(sysdate,'yyyy'))+
            (select nvl(sum(qty_d),0)-nvl(sum(qty_k),0)
            from SKVIEW_IOSTOCKWHS 
            where product_id=c.product_id
            AND to_char(txn_date,'yyyy')=TO_CHAR(sysdate,'yyyy'))  )saldo,
            nvl(   (select (sum(
            /*persen1*/(nvl(qty,0)*nvl(cost_price,0))/**/-/**/((nvl(qty,0)*nvl(cost_price,0))*nvl(dtl_persen,0)/100)/**/-/**/nvl(dtl_diskon,0)-
            /*persen2*/(((nvl(qty,0)*nvl(cost_price,0))/**/-/**/((nvl(qty,0)*nvl(cost_price,0))*nvl(dtl_persen,0)/100)/**/-/**/nvl(dtl_diskon,0))*
            (nvl(dtl_persen1,0)/100))-/**/nvl(dtl_diskon1,0))/sum(qty)) 
            from sktxn_rcvdtls z
            where z.rcv_no= (select rcv_no from skview_lastrcv y where y.product_id=z.product_id)
            and z.product_id=c.product_id),0)cost_price_after_Disc
from skmst_products c
/

CREATE OR REPLACE VIEW SKVIEW_SCPOLIS ("SC_POLI_STATUS_", "SC_POLI_KET", "DAY_ID", "DAY_DESC", "POLI_ID", "DR_ID", "SHIFT", "MULAI_PRAKTEK", "SELESAI_PRAKTEK", "PELAYANAN_PERP_ASIEN", "NO_URUT", "KUOTA", "KD_POLI_BPJS", "KD_DR_BPJS", "DR_NAME", "POLI_DESC") AS
SELECT
    a.SC_POLI_STATUS_,
    a.SC_POLI_KET,
    a.DAY_ID,
    b.DAY_DESC,
    a.POLI_ID,
    a.DR_ID,
    a.SHIFT,
    a.MULAI_PRAKTEK,
    a.SELESAI_PRAKTEK,
    a.PELAYANAN_PERP_ASIEN,
    a.NO_URUT,
    a.KUOTA,
    (SELECT kd_poli_bpjs FROM skmst_polis   WHERE poli_id = a.poli_id) kd_poli_bpjs,
    (SELECT kd_dr_bpjs   FROM skmst_doctors WHERE dr_id   = a.dr_id)   kd_dr_bpjs,
    (SELECT dr_name      FROM skmst_doctors WHERE dr_id   = a.dr_id)   dr_name,
    (SELECT poli_desc    FROM skmst_polis   WHERE poli_id = a.poli_id) poli_desc
FROM SKMST_SCPOLIS a, SKMST_SCDAYS b
WHERE a.day_id = b.day_id
/

CREATE OR REPLACE VIEW SKVIEW_SLSHDRS ("SLS_NO", "SLS_STATUS", "SLS_DATE", "CM_ID", "CM_NAME", "SLS_DESC", "SLS_DISKON", "CHECK_BOXSTATUS", "VCOUNT") AS
SELECT A.sls_no,sls_status,sls_date,A.cm_id,cm_name,sls_desc,sls_diskon,check_boxstatus,vcount
FROM SKTXN_SLSHDRS A,SKMST_CUSTOMERS b
WHERE A.cm_id=b.cm_id
/

CREATE OR REPLACE VIEW SKVIEW_SLSPAYS ("SLS_STATUS", "CM_ID", "VCOUNT", "SLS_NO", "TOTAL_BAYAR_MIN_TITIP") AS
select sls_status,b.cm_id,vcount,a.sls_no,(sum(/**/(nvl(qty,0)*nvl(sales_price,0))/**/-/**/((nvl(qty,0)*nvl(sales_price,0))*nvl(dtl_persen,0)/100)/**/-/**/nvl(dtl_diskon,0)/**/))-nvl(b.sls_diskon,0)-(select nvl(sum(slsp_value),0) from sktxn_slspayments where SLS_no=a.sls_no) total
from sktxn_slsdtls a,sktxn_slshdrs b
where a.sls_no=b.sls_no
group by sls_status,cm_id,vcount,a.sls_no,b.sls_diskon
order by vcount,a.sls_no
/

-- Pastikan tidak ada yang INVALID
SELECT object_name, status FROM user_objects WHERE object_type = 'VIEW' AND status <> 'VALID'
/
