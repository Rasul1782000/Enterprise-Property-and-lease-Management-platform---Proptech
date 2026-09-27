import { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.enterprise.propertylease',
  appName: 'Enterprise Property & Lease Management',
  webDir: 'dist',
  server: {
    androidScheme: 'https',
    iosScheme: 'https',
    url: 'http://localhost:8080',
    cleartext: true
  },
  plugins: {
    Camera: {
      permissions: ['camera']
    },
    Device: {
      enabled: true
    },
    Filesystem: {
      enabled: true
    },
    LocalNotifications: {
      enabled: true
    },
    PushNotifications: {
      enabled: true
    },
    Splash: {
      launchShowDuration: 3000,
      backgroundColor: '#FFFFFF',
      fadeOutDuration: 1000
    }
  }
};

export default config;