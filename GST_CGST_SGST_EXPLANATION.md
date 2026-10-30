# Testing Guide: CGST 9.0% & SGST 9.0% (Step-by-Step UI Flow)

---

## 🎯 आसान टेस्टिंग फ्लो (Button to Button)

```
[1. Company Details] ➜ State Set करो (उदा. Maharashtra)
         ⬇
[2. Service Invoice] ➜ Click "+ New Invoice" Button
         ⬇
[3. Form Fill]       ➜ Customer चुनिए + Amount डालिए + GST Rate: "18% GST" सेलेक्ट कीजिए
         ⬇
[4. Submit]          ➜ Click "Create Invoice" Button
         ⬇
[5. Print Check]     ➜ Table में Invoice के "Print (🖨️)" Button पर क्लिक करो
         ⬇
[6. Result Screen]   ➜ Item नाम के नीचे दिखेगा: [CGST 9.0%] [SGST 9.0%]
```

---

## 🧪 4 आसान स्टेप्स में टेस्ट करें:

### स्टेप 1: कंपनी का State सेट करें
* **कहाँ जाएँ:** Left Menu ➜ **Settings / Company Details** (`company-details.php`)
* **क्या करें:** **State** वाले बॉक्स में अपना राज्य लिखें (जैसे: `Maharashtra`) ➜ **Save** बटन दबाएँ।

---

### स्टेप 2: नया सर्विस इनवॉइस बनाएँ
* **कहाँ जाएँ:** Left Menu ➜ **Service Invoice** (`service_invoice.php`)
* **बटन दबाएँ:** ऊपर नीले रंग का **`+ New Invoice`** बटन क्लिक करें।
* **फॉर्म में यह भरें:**
  1. **Select Customer:** कोई भी कस्टमर चुनें (ध्यान रहे उसका State भी `Maharashtra` होना चाहिए)।
  2. **Service Name:** कुछ भी नाम लिखें (जैसे: `Consulting`).
  3. **Amount:** `1000` डालें।
  4. **GST Rate Dropdown:** **`18% GST`** सेलेक्ट करें।
* **बटन दबाएँ:** नीचे **`Create Invoice`** बटन पर क्लिक करें।

---

### स्टेप 3: प्रिंट बटन पर क्लिक करें (Result देखें)
* इनवॉइस लिस्ट टेबल में उस इनवॉइस के सामने दिए गए **Print (🖨️)** बटन पर क्लिक करें।
* आपके सामने [service_invoice_print.php](file:///c:/xampp/htdocs/Crediable/service_invoice_print.php) खुलेगा।

---

### 🔍 स्टेप 4: स्क्रीन पर क्या चेक करना है?
1. **Service Description के नीचे:**
   * नीले रंग के 2 टैग दिखेंगे: **`CGST 9.0%`** और **`SGST 9.0%`**
2. **Right Side Totals में:**
   * `CGST : ₹90.00`
   * `SGST : ₹90.00`
   * `Total GST : ₹180.00`
3. **Bottom GST Summary Table में:**
   * `CGST Rate: 9.0%` | `CGST Amt: ₹90.00`
   * `SGST Rate: 9.0%` | `SGST Amt: ₹90.00`

---

## 💡 स्टेटस (Status):
✅ **100% Complete और Working है!** आप ऊपर दिए गए बटन क्लिक करके तुरंत लाइव चेक कर सकते हैं।
