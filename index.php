<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kling AI - Генератор изображений и видео</title>
    <style>
        /* Сброс стандартных отступов и базовые настройки */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        /* Основной стилизованный блок в стиле Web 2.0 */
        .container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 40px;
            max-width: 800px;
            width: 100%;
            box-shadow: 
                0 20px 60px rgba(0, 0, 0, 0.3),
                0 0 0 1px rgba(255, 255, 255, 0.1) inset;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        /* Эффект при наведении на блок */
        .container:hover {
            transform: translateY(-5px);
            box-shadow: 
                0 25px 80px rgba(0, 0, 0, 0.4),
                0 0 0 1px rgba(255, 255, 255, 0.15) inset;
        }

        /* Заголовок с градиентом */
        h1 {
            background: linear-gradient(90deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            font-size: 3em;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        /* Основной абзац с описанием */
        .lead {
            font-size: 1.3em;
            color: #4a5568;
            text-align: center;
            margin-bottom: 40px;
            line-height: 1.6;
            padding: 0 10px;
        }

        /* Стили для карточек-преимуществ */
        .feature {
            background: linear-gradient(to right, #f7fafc, #edf2f7);
            border-radius: 18px;
            padding: 25px;
            margin-bottom: 25px;
            border-left: 5px solid #667eea;
            transition: all 0.3s ease;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.1);
        }

        .feature:hover {
            background: linear-gradient(to right, #ffffff, #f7fafc);
            transform: translateX(10px);
            box-shadow: 0 12px 25px rgba(102, 126, 234, 0.2);
        }

        /* Заголовки внутри карточек */
        .feature strong {
            color: #2d3748;
            font-size: 1.4em;
            display: block;
            margin-bottom: 10px;
            font-weight: 700;
        }

        /* Текст внутри карточек */
        .feature p {
            color: #718096;
            line-height: 1.7;
            font-size: 1.1em;
        }

        /* Стили для иконок-эмодзи */
        .emoji {
            font-size: 1.5em;
            margin-right: 10px;
            vertical-align: middle;
        }

        /* Ссылка на Telegram бота */
        .telegram-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0088cc, #006699);
            color: white;
            text-decoration: none;
            padding: 18px 35px;
            border-radius: 50px;
            font-size: 1.3em;
            font-weight: 600;
            margin: 40px auto 0;
            transition: all 0.3s ease;
            box-shadow: 0 10px 25px rgba(0, 136, 204, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.2);
            width: fit-content;
        }

        .telegram-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(0, 136, 204, 0.4);
            background: linear-gradient(135deg, #0099e6, #0088cc);
        }

        /* Центрирование ссылки */
        .link-container {
            display: flex;
            justify-content: center;
            width: 100%;
        }

        /* Адаптивность для мобильных устройств */
        @media (max-width: 768px) {
            .container {
                padding: 30px 20px;
                margin: 15px;
            }
            
            h1 {
                font-size: 2.3em;
            }
            
            .lead {
                font-size: 1.1em;
            }
            
            .feature {
                padding: 20px;
            }
            
            .telegram-link {
                padding: 15px 25px;
                font-size: 1.1em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Image to video</h1>
        
        <p class="lead">🤖 Генератор изображений и видео Kling AI. Самый быстрый и дешевый результат!</p>
        
        <div class="feature">
            <strong><span class="emoji">🎨</span>Генерация изображений</strong>
            <p>Превращаю ваши идеи в уникальные картины с помощью Midjourney. Современные нейросети создают изображения любого стиля по вашему описанию.</p>
        </div>
        
        <div class="feature">
            <strong><span class="emoji">🎬</span>Создание видео по фото</strong>
            <p>Загрузите свою фотографию → опишите действие → получайте анимированное видео через Kling AI! Профессиональная анимация за считанные минуты.</p>
        </div>
        
        <div class="feature">
            <strong><span class="emoji">⚡️</span>Быстро и удобно</strong>
            <p>Русский интерфейс, мгновенная обработка, поддержка всех популярных AI-моделей в одном боте. Работает прямо в Telegram без сложных установок.</p>
        </div>
        
        <div class="link-container">
            <a href="https://t.me/photo2live_bot" class="telegram-link" target="_blank">
                @photo2live_bot в Telegram
            </a>
        </div>
    </div>
</body>
</html>