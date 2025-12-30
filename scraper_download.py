# cloudscraper.py
import sys
import cloudscraper
import time
import os
from urllib.parse import urlparse

def download_file(url, file_path, retries=3, delay=2):
    """Скачивание файла с повторными попытками"""
    
    for attempt in range(retries):
        try:
            # Создаем директорию если её нет
            os.makedirs(os.path.dirname(file_path), exist_ok=True)
            
            # Создаем scraper с настройками
            scraper = cloudscraper.create_scraper(
                browser={
                    'browser': 'chrome',
                    'platform': 'linux',
                    'mobile': False
                }
            )
            
            # Добавляем заголовки
            headers = {
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language': 'en-US,en;q=0.5',
                'Accept-Encoding': 'gzip, deflate, br',
                'Connection': 'keep-alive',
                'Upgrade-Insecure-Requests': '1',
            }
            
            # Делаем запрос с таймаутом
            response = scraper.get(
                url, 
                headers=headers,
                timeout=30,
                allow_redirects=True
            )
            
            # Проверяем статус
            if response.status_code == 200:
                # Проверяем что это действительно файл, а не страница
                content_type = response.headers.get('Content-Type', '')
                
                if 'text/html' in content_type and not url.endswith(('.png', '.jpg', '.jpeg', '.gif')):
                    print(f"3: Получена HTML страница вместо файла. Content-Type: {content_type}")
                    return False
                
                # Сохраняем файл
                with open(file_path, 'wb') as f:
                    f.write(response.content)
                
                # Проверяем размер файла
                file_size = os.path.getsize(file_path)
                if file_size > 0:
                    print(f'1: Файл сохранен. Размер: {file_size} байт')
                    return True
                else:
                    print(f'4: Файл сохранен, но пустой')
                    os.remove(file_path)  # Удаляем пустой файл
                    return False
                    
            elif response.status_code == 404:
                print(f'5: Файл не найден (404)')
                return False
            elif response.status_code == 403:
                print(f'6: Доступ запрещен (403)')
                return False
            elif response.status_code == 429:
                print(f'7: Слишком много запросов (429). Попытка {attempt + 1}/{retries}')
                time.sleep(delay * (attempt + 1))  # Увеличиваем задержку
                continue
            else:
                print(f'8: HTTP ошибка: {response.status_code}')
                if attempt < retries - 1:
                    time.sleep(delay)
                    continue
                return False
                
        except cloudscraper.exceptions.CloudflareChallengeError as e:
            print(f'9: Ошибка Cloudflare: {str(e)}')
            if attempt < retries - 1:
                time.sleep(delay * 2)
                continue
            return False
            
        except Exception as e:
            print(f'10: Общая ошибка: {type(e).__name__}: {str(e)}')
            if attempt < retries - 1:
                time.sleep(delay)
                continue
            return False
    
    return False

if __name__ == "__main__":
    if len(sys.argv) > 2:
        url = sys.argv[1]
        file_path = sys.argv[2]
        
        result = download_file(url, file_path)
        if result:
            print(1);
        else: 
            print(0);
            sys.exit(0)  # Возвращаем 0 при ошибке
    else:
        print('0: Недостаточно аргументов')
        print('Использование: python3 cloudscraper.py <url> <file_path>')
        sys.exit(0)