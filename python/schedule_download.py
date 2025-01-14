import schedule
import time
import requests
from datetime import datetime

def do_thing():
    print("Hello there")


schedule.every(1).minutes.do(do_thing)

while True:
    schedule.run_pending()
    time.sleep(1)