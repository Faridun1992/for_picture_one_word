import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig(async() => {

   return {
       plugins: [
           laravel({
               input: [
                   'resources/js/app.js',
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
               host: 'project.loc',
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
