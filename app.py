# Web application
from flask import Flask, render_template, request, redirect, jsonify, flash
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


app = Flask(__name__)
app.secret_key = "my_secret_key"


client_id = '173e5603-6107-4521-a465-5b9dc86b2e95'


token_url = 'https://token.testing.heureka.health/oauth2/token'
auth_url = 'https://portal.testing.heureka.health/authorization'
configuration_url = 'https://api.testing.heureka.health/api-configuration'

mySession = {}
mySession['access_token'] = None
mySession['refresh_token'] = None
mySession['access_token_expire'] = 0



##############################################  WEBPAGES  ##############################################

@app.route('/', methods=['GET', 'POST'])
def login():
    if request.method == 'POST':
        username = request.form['username']
        password = request.form['password']

        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)

        cursor.execute("SELECT * FROM user_credentials WHERE username = %s", (username,))
        user = cursor.fetchone()

        if not user:
            flash("User doesn’t exist")
            return render_template('login.html')

        if verify_password(password, user['password']):

            return connect_to_heureka()
        else:
            flash("Wrong password!")
            #return render_template('login.html')
        
    return render_template('login.html')



@app.route('/callback', methods=['GET', 'POST'])
def redirected_page():
    auth_code = request.args.get('code')
    if not auth_code:
        return 'Authorization failed. No code provided.', 400

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
            decoded_token = jwt.decode(access_token, options={"verify_signature": False})
            sub_claim = decoded_token.get('sub')
            return jsonify(sub_claim)
        else:
            return f"Failed to retrieve token. Status code: {response.status_code}, Error: {response.text}", response.status_code

    except requests.exceptions.RequestException as e:
        return f"An error occurred while requesting the token: {str(e)}", 500



@app.route('/heureka_api', methods=['GET'])
def use_api():
    access_token = get_access_token()

    if not access_token:
        return jsonify({"error": "Could not obtain access token"}), 401

    headers = {'Authorization': f'Bearer {access_token}'}
    response = requests.get("https://api.example.com/protected", headers=headers)

    if response.status_code == 200:
        return jsonify(response.json())
    else:
        return jsonify({"error": "Failed to access the API", "details": response.json()}), response.status_code





##############################################  TOKEN FUNCTIONS  ##############################################

def get_new_access_token():
    refresh_token = mySession.get('refresh_token')

    if not refresh_token:
        return jsonify({"error": "No refresh token available"}), 401


    response = requests.post(
        token_url,
        data={
            "grant_type": "refresh_token",
            "refresh_token": refresh_token,
            "client_id": client_id
        },
    )

    if response.status_code == 200:
        token_data = response.json()
        mySession['access_token'] = token_data['access_token']
        mySession['access_token_expiry'] = time.time() + token_data['expires_in']
        if 'refresh_token' in token_data:
            mySession['refresh_token'] = token_data['refresh_token']
        return token_data['access_token']
    else:
        return jsonify({"error": "Failed to refresh token"}), 401



def get_access_token():
    access_token = mySession.get('access_token')
    expiry = mySession.get('access_token_expire', 0)

    if not access_token or time.time() > expiry:
        access_token = get_new_access_token()

    return access_token




##############################################  OTHER FUNCTIONS  ##############################################

def get_db_connection():
    conn = mysql.connector.connect(
        host = 'localhost',
        user = 'alex',
        password = 'password',
        database = 'fire_heureka_credentials'
    )
    return conn



def connect_to_heureka():
    random_state = secrets.token_urlsafe(32)
    redirect_url = 'http://localhost:5000/callback'
    url = f'{auth_url}/grant?client_id={client_id}&state={random_state}&redirect_uri={redirect_url}'

    return redirect(url)



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