import subprocess

from flask import Flask, render_template, request, redirect, url_for, session, jsonify
import secrets
import requests
import jwt
import mysql.connector


app = Flask(__name__)
app.secret_key = "my_secret_key"


def get_db_connection():
    conn = mysql.connector.connect(
        host = 'localhost',
        user = 'debian',
        password = 'password',
        database = 'fire_heureka_credentials'
    )
    return conn



def connect_to_heureka():
    client_id = '173e5603-6107-4521-a465-5b9dc86b2e95'
    random_state = secrets.token_urlsafe(32)
    redirect_url = 'http://localhost:5000/callback'
    url = f'https://portal.testing.heureka.health/authorization/grant?client_id={client_id}&state={random_state}&redirect_uri={redirect_url}'

    return redirect(url)


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
            error = "User doesn\'t exist"
            return render_template('login.html', error=error)

        if password == user['password']:
            return connect_to_heureka()
            #return redirect('https://portal.testing.heureka.health/authorization/grant?client_id=CLIENT_ID&state=RANDOM_ANTI_CSRF_STRING&redirect_uri=https://example.com/callback')
        else:
            error = "Wrong password"
            return render_template('login.html', error=error)
        
    return render_template('login.html')



@app.route('/callback', methods=['GET', 'POST'])
def redirected_page() :
    auth_code = request.args.get('code')
    if not auth_code:
        return 'Authorization failed. No code provided.', 400

    token_url = 'https://token.testing.heureka.health/oauth2/token'
    client_id = '173e5603-6107-4521-a465-5b9dc86b2e95'
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




if __name__ == '__main__':
    app.run(debug=True)





def run_parser():
    project_dir = "/home/alex/Desktop/json_xml_parser/json_xml_parser"

    subprocess.run(["mvn", "clean", "compile"], cwd=project_dir)
    subprocess.run(["mvn", "exec:java", "-Dexec.mainClass=com.example.App"], cwd=project_dir)

#run_parser()