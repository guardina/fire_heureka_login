-- SELECT PATIENTS GROUPED BY SEX + AGE BOTH DB

SELECT 
    birth_year, 
    LOWER(sex) AS sex,
    SUM(CASE WHEN source = 'fire5_small_vitomed' THEN patient_count ELSE 0 END) AS count_small_vitomed,
    SUM(CASE WHEN source = 'fire5_big_vitomed' THEN patient_count ELSE 0 END) AS count_big_vitomed
FROM (
    -- Count patients from fire5_small_vitomed
    SELECT 'fire5_small_vitomed' AS source, 
           birth_year, 
           LOWER(sex) AS sex, 
           COUNT(*) AS patient_count
    FROM fire5_small_vitomed.a_patient
    WHERE birth_year IS NOT NULL AND sex IS NOT NULL
    GROUP BY birth_year, LOWER(sex)

    UNION ALL

    -- Count DISTINCT patients from fire5_big_vitomed
    SELECT 'fire5_big_vitomed' AS source, 
           birth_year, 
           LOWER(sex) AS sex, 
           COUNT(DISTINCT pat_sw_id) AS patient_count
    FROM fire5_big_vitomed.a_patient
    WHERE birth_year IS NOT NULL AND sex IS NOT NULL
    GROUP BY birth_year, LOWER(sex)
) AS combined
GROUP BY birth_year, sex
ORDER BY birth_year ASC, sex ASC;





-- SELECT PATIENTS THAT APPEAR ONLY ONCE IN BOTH DATABASES

SELECT 
    birth_year, 
    LOWER(sex) AS sex,
    SUM(CASE WHEN source = 'fire5_small_vitomed' THEN patient_count ELSE 0 END) AS count_small_vitomed,
    SUM(CASE WHEN source = 'fire5_big_vitomed' THEN patient_count ELSE 0 END) AS count_big_vitomed
FROM (
    -- Count patients from fire5_small_vitomed
    SELECT 'fire5_small_vitomed' AS source, 
           birth_year, 
           LOWER(sex) AS sex, 
           COUNT(*) AS patient_count
    FROM fire5_small_vitomed.a_patient
    WHERE birth_year IS NOT NULL AND sex IS NOT NULL
    GROUP BY birth_year, LOWER(sex)

    UNION ALL

    -- Count DISTINCT patients from fire5_big_vitomed
    SELECT 'fire5_big_vitomed' AS source, 
           birth_year, 
           LOWER(sex) AS sex, 
           COUNT(DISTINCT pat_sw_id) AS patient_count
    FROM fire5_big_vitomed.a_patient
    WHERE birth_year IS NOT NULL AND sex IS NOT NULL
    GROUP BY birth_year, LOWER(sex)
) AS combined
GROUP BY birth_year, sex
HAVING count_small_vitomed = 1 AND count_big_vitomed = 1
ORDER BY birth_year ASC, sex ASC;



-- SELECT GROUPS OF SEX + AGE THAT APPEARS AT MOST 3 TIMES IN EACH DB

SELECT 
    birth_year, 
    LOWER(sex) AS sex,
    SUM(CASE WHEN source = 'fire5_small_vitomed' THEN patient_count ELSE 0 END) AS count_small_vitomed,
    SUM(CASE WHEN source = 'fire5_big_vitomed' THEN patient_count ELSE 0 END) AS count_big_vitomed
FROM (
    -- Count patients from fire5_small_vitomed
    SELECT 'fire5_small_vitomed' AS source, 
           birth_year, 
           LOWER(sex) AS sex, 
           COUNT(*) AS patient_count
    FROM fire5_small_vitomed.a_patient
    WHERE birth_year IS NOT NULL AND sex IS NOT NULL
    GROUP BY birth_year, LOWER(sex)

    UNION ALL

    -- Count DISTINCT patients from fire5_big_vitomed
    SELECT 'fire5_big_vitomed' AS source, 
           birth_year, 
           LOWER(sex) AS sex, 
           COUNT(DISTINCT pat_sw_id) AS patient_count
    FROM fire5_big_vitomed.a_patient
    WHERE birth_year IS NOT NULL AND sex IS NOT NULL
    GROUP BY birth_year, LOWER(sex)
) AS combined
GROUP BY birth_year, sex
HAVING count_small_vitomed <= 3 AND count_big_vitomed <= 3
ORDER BY birth_year ASC, sex ASC;


-- SELECT pat_sw_id OF THE PATIENTS WITH SAME SEX + AGE THAT APPEAR IN BOTH DB ONLY ONCE

SELECT 
    birth_year, 
    LOWER(sex) AS sex,
    SUM(CASE WHEN source = 'fire5_small_vitomed' THEN patient_count ELSE 0 END) AS count_small_vitomed,
    SUM(CASE WHEN source = 'fire5_big_vitomed' THEN patient_count ELSE 0 END) AS count_big_vitomed,
    GROUP_CONCAT(DISTINCT CASE WHEN source = 'fire5_small_vitomed' THEN pat_sw_id ELSE NULL END) AS pat_sw_ids_small_vitomed,
    GROUP_CONCAT(DISTINCT CASE WHEN source = 'fire5_big_vitomed' THEN pat_sw_id ELSE NULL END) AS pat_sw_ids_big_vitomed
FROM (
    -- Get patient counts and pat_sw_id from fire5_small_vitomed
    SELECT 'fire5_small_vitomed' AS source, 
           birth_year, 
           LOWER(sex) AS sex, 
           COUNT(*) AS patient_count,
           pat_sw_id
    FROM fire5_small_vitomed.a_patient
    WHERE birth_year IS NOT NULL AND sex IS NOT NULL
    GROUP BY birth_year, LOWER(sex), pat_sw_id

    UNION ALL

    -- Get patient counts and distinct pat_sw_id from fire5_big_vitomed
    SELECT 'fire5_big_vitomed' AS source, 
           birth_year, 
           LOWER(sex) AS sex, 
           COUNT(DISTINCT pat_sw_id) AS patient_count,
           pat_sw_id
    FROM fire5_big_vitomed.a_patient
    WHERE birth_year IS NOT NULL AND sex IS NOT NULL
    GROUP BY birth_year, LOWER(sex), pat_sw_id
) AS combined
GROUP BY birth_year, sex
HAVING count_small_vitomed = 1 AND count_big_vitomed = 1
ORDER BY birth_year ASC, sex ASC;
