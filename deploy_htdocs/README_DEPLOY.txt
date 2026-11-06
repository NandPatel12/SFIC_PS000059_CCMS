=====================================================
  Gandhidham Streetlight CCMS - InfinityFree Deployment
  Fully Built: Phases 1, 2, 3, 4 & 5
  Target Domain: http://ccms.gt.tc
=====================================================

STEP 1: IMPORT DATABASE (phpMyAdmin)
-----------------------------------------------------
1. Open your InfinityFree Control Panel (cPanel).
2. Click on "phpMyAdmin" and connect to your database:
   Database Name: if0_43371701_ccms
3. Click the "Import" tab at the top.
4. Select "infinityfree_import.sql" from this package.
5. Click "Import / Go" at the bottom.
   (All 16 tables, astronomical schedules, work orders, tariffs, and users will be imported).

STEP 2: UPLOAD ZIP TO HTDOCS
-----------------------------------------------------
1. In InfinityFree Control Panel, open "Online File Manager".
2. Navigate to "htdocs" (or "ccms.gt.tc/htdocs").
3. Delete any host default files (like default.php or index2.html).
4. Upload "ccms_deploy_htdocs.zip" and click "Extract / Unzip".
   Ensure .htaccess, .env, and index.php are at the root of htdocs.

STEP 3: OPEN YOUR APPLICATION
-----------------------------------------------------
Navigate to: http://ccms.gt.tc/

Demo Credentials:
- Super Administrator: admin / Admin@1234
- Electrical Engineer: engineer_patel / Admin@1234
- Line Supervisor: sup_sharma / Admin@1234
- Field Contractor: contractor_apex / Admin@1234
