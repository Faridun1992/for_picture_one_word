import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import fg from 'fast-glob';

export default defineConfig(async() => {

    const guest_files = await fg('resources/js/guest/*.js');
    const admin_files = await fg('resources/js/admin/*.js');
    const user_files = await fg('resources/js/user/*.js');

    const guest_scss = await fg('resources/css/guest/*.scss');
    const admin_scss = await fg('resources/css/admin/*.scss');
    const user_scss = await fg('resources/css/user/*.scss');

   return {
       plugins: [
           laravel({
               input: [
                   ...guest_files,
                   ...admin_files,
                   ...user_files,
                   ...guest_scss,
                   ...admin_scss,
                   ...user_scss
               ],

               refresh: true,
           }),
       ],
       server: {
           cors: true,
           host: '0.0.0.0',
           port: 3000,
           open: false,
           hmr: {
               host: 'project-x.loc',
               protocol: 'ws'
           }
       },
       base: '',
       build: {
           chunkSizeWarningLimit: 1500,
       },
       css: {
           preprocessorOptions: {
               scss: {
                   api: 'modern-compiler',
               },
           },
       },
   }
});
