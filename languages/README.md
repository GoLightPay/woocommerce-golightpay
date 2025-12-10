# 多语言支持

## 翻译文件

- `woocommerce-golightpay-zh_CN.po` - 中文（简体）翻译源文件
- `woocommerce-golightpay-zh_CN.mo` - 编译后的中文翻译文件（二进制）

## 编译翻译文件

### 方法 1: 使用 msgfmt（推荐）

如果系统已安装 gettext：

```bash
cd languages
msgfmt woocommerce-golightpay-zh_CN.po -o woocommerce-golightpay-zh_CN.mo
```

### 方法 2: 使用在线工具

1. 访问 https://po2mo.net/ 或类似工具
2. 上传 `.po` 文件
3. 下载编译后的 `.mo` 文件

### 方法 3: 在 WordPress 中自动编译

WordPress 5.0+ 可以自动加载 `.po` 文件，但 `.mo` 文件性能更好。

## 添加新语言

1. 复制 `woocommerce-golightpay-zh_CN.po` 为新语言文件（如 `woocommerce-golightpay-en_US.po`）
2. 修改文件头部的 `Language` 字段
3. 翻译所有 `msgstr` 字段
4. 编译为 `.mo` 文件

## 支持的语言

- 中文（简体）: `zh_CN`
- 英文（默认）: 使用原始字符串，无需翻译文件

## 更新翻译

修改 `.po` 文件后，需要重新编译为 `.mo` 文件才能生效。
