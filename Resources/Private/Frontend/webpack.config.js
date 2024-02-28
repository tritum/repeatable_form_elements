const path = require('path')

module.exports = (env, argv) => {
    return {
        entry: {
            global: [
                './src/global.js',
            ],
            bundle: [
                './src/bundle.js'
            ]
        },
        output: {
            path: path.resolve(__dirname, '..', '..', 'Public', 'JavaScript', 'Form', 'Frontend'),
            filename: 'RepeatableContainer.[name].js',
            // publicPath: '/typo3conf/ext/ctw_template/Resources/Public/assets/',
            clean: true
        },
        module: {
            rules: [
                {
                    test: /\.js$/,
                    exclude: /node_modules/,
                    use: {
                        loader: 'babel-loader'
                    }
                }
            ]
        },
        optimization: {
            // splitChunks: {
            //     chunks: 'all',
            //     minSize: 1000,
            //     minChunks: 1,
            //     cacheGroups: {
            //         vendor: {
            //             test: /[\\/]node_modules[\\/]/,
            //             priority: 1,
            //             name: 'vendor'
            //         }
            //     }
            // }
        },
        devServer: {
            host: '0.0.0.0',
            hot: true,
            writeToDisk: true
        }
    }
}
