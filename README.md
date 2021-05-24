# Introduction
Convert Python SDK to PHP https://www.upwork.com/jobs/~016896a7df46556783

Start with this: https://github.com/veryfi/veryfi-python
as a guide and convert it to a PHP SDK.

Please make sure:
- your code is clean and concise
- includes internal documentation
- includes unit tests

要求转换python的sdk为php，其实很容易。但要求有代码规范和单元测试，文档的话照着人家来就可以了。

# 学习点
这个python库的注释写的非常的标准，值得参考学习。
```python
  def process_document(self, file_path, categories=None, delete_after_processing=False):
      """
      Process Document and extract all the fields from it
      :param file_path: Path on disk to a file to submit for data extraction
      :param categories: List of categories Veryfi can use to categorize the document
      :param delete_after_processing: Delete this document from Veryfi after data has been extracted
      :return: Data extracted from the document
      """
      endpoint_name = "/documents/"
      if not categories:
          categories = self.CATEGORIES
      file_name = os.path.basename(file_path)
      with open(file_path, "rb") as image_file:
          base64_encoded_string = base64.b64encode(image_file.read()).decode("utf-8")
      request_arguments = {
          "file_name": file_name,
          "file_data": base64_encoded_string,
          "categories": categories,
          "auto_delete": delete_after_processing,
      }
      document = self._request("POST", endpoint_name, request_arguments)
      return document
```
