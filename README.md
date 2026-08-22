# 🖼️ Profile Cover for Flarum
Adds the ability to upload a cover image to a profile. **Supports Flarum 2.x only**


## 🚀 Features
- 🖼️ Upload cover images to user profiles
- 🎞️ GIF support — animated covers
- 📐 Proportional thumbnails — no cropping
- ⚙️ Configurable max file size in admin panel
- 📝 Compatible with FoF Blog — cover shows behind the author card on blog posts


## 📸 Screenshots
<img width="1230" height="650" alt="Profile" src="https://github.com/user-attachments/assets/9cd9f6f3-855c-40f9-a8d0-d70261edabfe" />

___

<img width="1300" height="170" alt="image" src="https://github.com/user-attachments/assets/c6a07c0f-f4aa-4acc-9f6a-1ded845a0409" />

___

<img width="1585" height="700" alt="image" src="https://github.com/user-attachments/assets/f4c3611c-c306-47c5-9ff6-3ca00fadc53e" />



## 📦 Installation
Run this in your Flarum root directory:
```
composer require forumaker/profile-cover:"*"
```


## 🔄 Upgrading from sycho/flarum-profile-cover

1. Uninstall `sycho/flarum-profile-cover`
2. Install `forumaker/profile-cover`
3. Run `php flarum migrate` — existing covers, settings and permissions are preserved automatically


## 🔗 Links
- [GitHub Repository](https://github.com/forumaker/profile-cover)
- [Packagist](https://packagist.org/packages/forumaker/profile-cover)
- [Discuss](https://discuss.flarum.org/d/39144-profile-cover-thumbnails-and-gif-support)


Fork of [sycho/flarum-profile-cover](https://github.com/SychO9/flarum-profile-cover) with GIF and WebP support, improved thumbnails and adjustable cover size
