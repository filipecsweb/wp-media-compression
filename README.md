# Plugin WordPress: Otimização de Imagens

Otimize as imagens do WordPress de forma ilimitada e completamente, sem pagar.

## Requerimentos
* PHP 7.2+
* WordPress 5.0+
* Para que o plugin consiga otimizar imagens é necessário que estejam instaladas no servidor as seguintes bibliotecas:  
`jpegoptim`  
`optipng`  
`pngquant`  
`gifsicle`  
`webp`

## Instruções de uso
* Pesquise no Google para instalar de maneira correta as bibliotecas no seu servidor.

* Exemplo de como fazer a instalação no Ubuntu:
```
apt-get update -y; \
apt-get install jpegoptim -y; \
apt-get install optipng -y; \
apt-get install pngquant -y; \
apt-get install gifsicle -y; \
apt-get install webp -y
```

* Desde que as bibliotecas acima estejam instaladas e funcionando, basta instalar e ativar o plugin para que as imagens comecem a ser automaticamente otimizadas.

* Se quiser otimizar imagens que já estavam na biblioteca, basta utilizar o WP-CLI da seguinte forma:
```
 wp media regenerate --yes
```

## Créditos
* https://github.com/spatie/image-optimizer
