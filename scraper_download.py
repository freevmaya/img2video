# cloudscraper.py
import sys
import cloudscraper

if len(sys.argv) > 2:

    scraper = cloudscraper.create_scraper()
    #url = 'https://cdn.midjourney.com/465acea3-64cc-45ab-81dd-501533b489f5/0_0.png'
    response = scraper.get(sys.argv[1])

    #print('Status Code:', response.status_code)
    #print('File size (bytes):', len(response.content))

    if response.status_code == 200:
        with open(sys.argv[2], 'wb') as f:
            f.write(response.content)
        print('1')
    else: print('0')
else: print('0')