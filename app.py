# Web application
from flask import Flask, render_template, request, redirect, jsonify, flash, session, url_for, send_file
import requests

# Security
import jwt
import secrets
import bcrypt

# Database
import mysql.connector

# General
import subprocess
import time
from datetime import datetime, timedelta
import os
import json
import io


app = Flask(__name__)
app.secret_key = "my_secret_key"


client_id = '173e5603-6107-4521-a465-5b9dc86b2e95'


token_url = 'https://token.testing.heureka.health/oauth2/token'
auth_url = 'https://portal.testing.heureka.health/authorization'
configuration_url = 'https://api.testing.heureka.health/api-configuration'


##############################################  WEBPAGES  ##############################################

@app.route('/', methods=['GET', 'POST'])
def login():
    if request.method == 'POST':
        username = request.form['username']
        password = request.form['password']

        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)

        #hash_pw = hash_password(password)
        #cursor.execute("INSERT INTO user_credentials(username, password) VALUES (%s, %s)", ("mock_user", hash_pw))
        #conn.commit()
        
        
        cursor.execute("SELECT * FROM user_credentials WHERE username = %s", (username,))
        user = cursor.fetchone()
        cursor.execute("""
        SELECT user_tokens.access_token
        FROM user_credentials
        JOIN user_tokens ON user_credentials.id = user_tokens.user_id
        WHERE user_credentials.username = %s"""
        , (username,))
        user_token = cursor.fetchone()

        if not user:
            flash("User doesn’t exist")
            return render_template('login.html')

        if verify_password(password, user['password']):
            session['user_id'] = user['id']
            if not user_token:
                return heureka_authorize()
            else:
                return redirect(url_for('management_hub'))
        else:
            flash("Wrong password!")
            return render_template('login.html')
        
    return render_template('login.html')



@app.route('/callback', methods=['GET', 'POST'])
def redirected_page():
    if session['mode']:
        if session['mode'] == 'authorize':
            auth_code = request.args.get('code')
            if not auth_code:
                return render_template('heureka_connection.html')

            redirect_uri = 'http://localhost:5000/callback'

            payload = {
                "grant_type": "authorization_code",
                "client_id": client_id,
                "redirect_uri": redirect_uri,
                "code": auth_code
            }


            try:
                response = requests.post(
                    token_url,
                    data=payload,
                    cert=("resources/fire.crt", "resources/fire.key"),
                    headers={"Content-Type": "application/x-www-form-urlencoded"}
                )
                
                if response.status_code == 200:
                    token_data = response.json()
                    access_token = token_data.get('access_token')
                    refresh_token = token_data.get('refresh_token')
                    installation_id = jwt.decode(access_token, options={"verify_signature": False}).get('sub')
                    expiration_time = token_data.get('expires_in')

                    save_token(access_token, refresh_token, expiration_time, 'insert')

                    conn = get_db_connection()
                    cursor = conn.cursor(dictionary=True)

                    user_id = session.get('user_id')

                    cursor.execute("""
                    UPDATE user_credentials SET installation_id = %s WHERE id = %s
                    """
                    , (installation_id, user_id))
                    conn.commit()

                    return redirect(url_for('change_password'))
                else:
                    return f"Failed to retrieve token. Status code: {response.status_code}, Error: {response.text}", response.status_code

            except requests.exceptions.RequestException as e:
                return f"An error occurred while requesting the token: {str(e)}", 500

        elif session['mode'] == 'update':
            return render_template('heureka_connection.html')

        elif session['mode'] == 'revoke':
            return render_template('heureka_connection.html')
    

@app.route('/change_password', methods=['GET', 'POST'])
def change_password():
    if request.method == 'POST':
        user_id = session.get('user_id')
        password = request.form['password']
        password_conf = request.form['password_conf']

        if (password == password_conf):
            conn = get_db_connection()
            cursor = conn.cursor(dictionary=True)

            hpassword = hash_password(password)

            cursor.execute("""
            UPDATE user_credentials
            SET password = %s
            WHERE id = %s
            """, (hpassword, user_id))
            conn.commit()

            return redirect(url_for('management_hub'))

    return render_template('change_password.html')



@app.route('/management_hub', methods=['GET', 'POST'])
def management_hub():
    return render_template('heureka_connection.html')





@app.route('/heureka_api', methods=['GET'])
def heureka_api():
    access_token = get_access_token()

    if not access_token:
        return jsonify({"error": "Could not obtain access token"}), 401

    configure_heureka()
    patients = get_patients_heureka()
    return patients

    #return render_template('heureka_connection.html')
    
    '''
    if response.status_code == 200:
        return jsonify(response.json())
    else:
        return jsonify({"error": "Failed to access the API", "details": response.json()}), response.status_code
    '''



@app.route('/heureka_authorize', methods=['GET', 'POST'])
def heureka_authorize():
    random_state = secrets.token_urlsafe(32)
    redirect_url = 'http://localhost:5000/callback'
    session['mode'] = 'authorize'
    url = f'{auth_url}/grant?client_id={client_id}&state={random_state}&redirect_uri={redirect_url}'

    return redirect(url)


@app.route('/heureka_update', methods=['GET', 'POST'])
def update_permissions():
    user_id = session.get('user_id')
    redirect_url = 'http://localhost:5000/callback'

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)
    cursor.execute("""
    SELECT installation_id
    FROM user_credentials
    WHERE id = %s
    """
    , (user_id,))

    installation_id = cursor.fetchone()['installation_id']
    session['mode'] = 'update'
    url = f'{auth_url}/update?installation_id={installation_id}&redirect_uri={redirect_url}'
   
    return redirect(url)


@app.route('/heureka_revoke', methods=['GET', 'POST'])
def remove_permissions():
    user_id = session.get('user_id')
    redirect_url = 'http://localhost:5000/callback'

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)
    cursor.execute("""
    SELECT installation_id
    FROM user_credentials
    WHERE id = %s
    """
    , (user_id,))

    installation_id = cursor.fetchone()['installation_id']
    session['mode'] = 'revoke'
    url = f'{auth_url}/revoke?installation_id={installation_id}&redirect_uri={redirect_url}'

    return redirect(url)


##############################################  TOKEN FUNCTIONS  ##############################################

def get_new_access_token():
    user_id = session.get('user_id')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)
    cursor.execute("""
    SELECT user_tokens.refresh_token
    FROM user_credentials
    JOIN user_tokens ON user_credentials.id = user_tokens.user_id
    WHERE user_credentials.id = %s
    """, (user_id,))

    user_token = cursor.fetchone()

    refresh_token = user_token['refresh_token']

    if not refresh_token:
        return jsonify({"error": "No refresh token available"}), 401


    response = requests.post(
        token_url,
        data={
            "grant_type": "refresh_token",
            "refresh_token": refresh_token,
            "client_id": client_id
        },
        cert=("resources/fire.crt", "resources/fire.key"),
        headers={"Content-Type": "application/x-www-form-urlencoded"}
    )

    if response.status_code == 200:
        token_data = response.json()
        save_token(token_data['access_token'], token_data['refresh_token'], token_data['expires_in'], 'update')
        return jsonify(token_data)
    else:
        print("Error refreshing token:", response.text)
        return jsonify({"error": "Failed to refresh token"}), 401



def get_access_token():
    user_id = session.get('user_id')
    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)
    cursor.execute("""
    SELECT user_tokens.access_token, user_tokens.token_expiry
    FROM user_credentials
    JOIN user_tokens ON user_credentials.id = user_tokens.user_id
    WHERE user_credentials.id = %s
    """, (user_id,))

    user_token = cursor.fetchone()

    token_expiry = user_token['token_expiry']
    access_token = user_token['access_token']
    current_time = datetime.fromtimestamp(time.time())

    if not access_token or current_time > token_expiry:
        access_token = get_new_access_token()

    return access_token



def save_token(access_token, refresh_token, token_expiry, mode):
    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    new_token_expiry = datetime.now() + timedelta(seconds=299)

    user_id = session.get('user_id')

    if (mode == 'insert'):
        cursor.execute("""
        INSERT INTO user_tokens (user_id, access_token, refresh_token, token_expiry) 
        VALUES (%s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE 
            access_token = VALUES(access_token),
            refresh_token = VALUES(refresh_token),
            token_expiry = VALUES(token_expiry),
            updated_at = CURRENT_TIMESTAMP
        """
        , (user_id, access_token, refresh_token, new_token_expiry))
        conn.commit()
    elif (mode == 'update'):
        cursor.execute("""
        UPDATE user_tokens
        SET 
            access_token = %s,
            refresh_token = %s,
            token_expiry = %s
        WHERE user_id = %s
        """
        , (access_token, refresh_token, new_token_expiry, user_id))
        conn.commit()



##############################################  OTHER FUNCTIONS  ##############################################

def get_db_connection():
    conn = mysql.connector.connect(
        host = 'localhost',
        user = 'debian',
        password = 'password',
        database = 'fire_heureka_credentials'
    )
    return conn


def configure_heureka():
    user_token = get_access_token()

    try:
        response = requests.get(
            configuration_url,
            cert=("resources/fire.crt", "resources/fire.key"),
            headers={"Authorization": f"Bearer {user_token}"}      
        )

        if response.status_code == 200:
            responseJson = response.json()
            session['fhirEndpoint'] = responseJson['fhirEndpoint']
            session['heurekaProxy'] = responseJson['proxy']
            session['healthcareProviderId'] = responseJson['healthcareProviderId']
            session['heurekaGrants'] = responseJson['grants']
            return response.json()
        else:
            return jsonify({
                "error": "Failed to fetch data",
                "status_code": response.status_code,
                "details": response.text
            }), response.status_code

    except requests.exceptions.RequestException as e:
        return jsonify({"error": f"An error occurred: {str(e)}"}), 500




def get_patients_heureka():
    os.environ['NO_PROXY'] = 'api.testing.heureka.health,authorize.testing.heureka.health,token.testing.heureka.health' 
    user_token = get_access_token()

    url = session['fhirEndpoint'] + '/Patient'
    cert = ('resources/fire.crt', 'resources/fire.key')
    ca_cert = 'resources/heureka-testing.pem'
    proxies = { 
        'https': 'http://tunnel.testing.heureka.health:7000'
    }
    headers={"Authorization": f"Bearer {user_token}"}  

    try:
        response = requests.get(
            url,
            proxies=proxies,
            cert=cert,
            verify=ca_cert,
            headers=headers
        )

        if response.status_code == 200:
            fileObj = io.BytesIO()  
            fileObj.write('{"resourceType" : "Bundle", "entry": ['.encode('utf-8'))

            bundle = json.loads(json.dumps(response.json()))

            entries = bundle['entry']

            for i, entry in enumerate(entries):
                patient = entry['resource']
                fileObj.write(json.dumps(patient).encode('utf-8'))
                fileObj.write(",".encode('utf-8'))
                elements_patient = get_elements_for_patient(patient['id'])
                fileObj.write(elements_patient.encode('utf-8'))

                if i < len(entries) - 1:
                    fileObj.write(",".encode('utf-8'))

            

            fileObj.write(']}'.encode('utf-8'))
            fileObj.seek(0)


            return send_file(
                fileObj,
                as_attachment=True,
                download_name="download.json",
                mimetype="text/plain"
            )

        else:
            print(f"Request failed with status code: {response.status_code}")
            print("Response:", response.text)
            return response.text
    except requests.exceptions.RequestException as e:
        print(f"An error occurred: {str(e)}")




# includes Observation, Condition, Medications
def get_elements_for_patient(patient_id):
    os.environ['NO_PROXY'] = 'api.testing.heureka.health,authorize.testing.heureka.health,token.testing.heureka.health' 
    user_token = get_access_token()
    url_suffixes = ["/Observation?patient=Patient/", "/Condition?patient=Patient/", "/MedicationStatement?subject=Patient/"]

    patient_info = ""

    for url_suffix in url_suffixes:
        url = session.get('fhirEndpoint') + url_suffix + patient_id
        cert = ('resources/fire.crt', 'resources/fire.key')
        ca_cert = 'resources/heureka-testing.pem'
        proxies = { 
            'https': 'http://tunnel.testing.heureka.health:7000'
        }
        headers={"Authorization": f"Bearer {user_token}"}

        try:
            response = requests.get(
                url,
                proxies=proxies,
                cert=cert,
                verify=ca_cert,
                headers=headers
            )

            if response.status_code == 200:
                element = json.loads(json.dumps(response.json()))
                patient_info = patient_info + json.dumps(element['entry'][0]) + ","

                #return patient_info
            else:
                #print(f"Request failed with status code: {response.status_code}")
                #print("Response:", response.text)
                return response.text
        except requests.exceptions.RequestException as e:
            print(f"An error occurred: {str(e)}")

    patient_info = patient_info[:-1]
    print("\n\n" + patient_info)
    return patient_info

def hash_password(password):
    salt = bcrypt.gensalt()
    hashed_password = bcrypt.hashpw(password.encode('utf-8'), salt)
    return hashed_password


def verify_password(provided_password, stored_password):
    return bcrypt.checkpw(provided_password.encode('utf-8'), stored_password)



##############################################  MAIN  ##############################################

if __name__ == '__main__':
    app.run(debug=True)



def run_parser():
    project_dir = "/home/alex/Desktop/json_xml_parser/json_xml_parser"

    subprocess.run(["mvn", "clean", "compile"], cwd=project_dir)
    subprocess.run(["mvn", "exec:java", "-Dexec.mainClass=com.example.App"], cwd=project_dir)

#run_parser()