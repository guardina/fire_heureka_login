<?php
    include "db.php";

    $conn1 = connect_to_db('fire5_xml');

    $patient_ids = [
        '000713913be775158f5060eecf9ae371d30a5b78629126b9f97573741881d7e2', 
        '00074f4f2ea875b2f4363cc6ebaa871c3f6a9f3d8191c9fc142b4dff6d6611d8',
        '0007f9871939d86da4386a7b9bc62caea132dd9053bcbd2983b13da4389864ab',
        '000AF638DEB4330EA34F41EB5A4E95A488E339BC690F27EAD31165579376F739',
        '000ba851258da401e07992ba246ffc6ad6540dbff577d050d04f2f171f9304d5', 
        '000cb689a9a237562ac36f62807e79236c693140622b484d156ce1041c6c8590',
        '000feb1d9479372b6074b33b3b7bcd142465ded0d01994519ec5dd1ccef05e87',
        '00109ea829a7800930aa987c35f4a751c407220444cd3c0ee117db36ea5e2b66'];


    foreach ($patient_ids as $patient_id) {
        $check_patient_table_query = 
        "   SELECT * 
            FROM fire5_xml.a_patient
            WHERE pat_sw_id = %s;
        ";

        
    }

    
?>