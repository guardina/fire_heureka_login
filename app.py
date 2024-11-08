import subprocess

from flask import Flask, render_template, request, redirect, url_for, session
import requests
import mysql.connector


app = Flask(__name__)
app.secret_key = "my_secret_key"


def get_db_connection():
    conn = mysql.connector.connect(
        host = 'localhost',
        user = 'alex',
        password = 'password',
        database = 'fire_heureka_credentials'
    )
    return conn


@app.route('/', methods=['GET', 'POST'])
def login():
    if request.method == 'POST':
        username = request.form['username']
        password = request.form['password']

        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)

        cursor.execute("SELECT * FROM users WHERE username = %s", (username,))
        user = cursor.fetchone()

        if not user:
            error = "User doesn\'t exist"
            return render_template('login.html', error=error)

        if password == user['password']:
            error = "Logged in"
            return render_template('login.html', error=error)
        else:
            error = "Wrong password"
            return render_template('login.html', error=error)
        
    return render_template('login.html')



if __name__ == '__main__':
    app.run(debug=True)





def run_parser():
    project_dir = "/home/alex/Desktop/json_xml_parser/json_xml_parser"

    subprocess.run(["mvn", "clean", "compile"], cwd=project_dir)
    subprocess.run(["mvn", "exec:java", "-Dexec.mainClass=com.example.App"], cwd=project_dir)

#run_parser()