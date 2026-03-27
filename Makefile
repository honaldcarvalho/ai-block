CC = gcc
CFLAGS = -Wall -Wextra `pkg-config --cflags gtk+-3.0` -O2
LIBS = `pkg-config --libs gtk+-3.0` -lpthread -ldl -lm
TARGET = ai_block

all: $(TARGET)

$(TARGET): ai_block.c sqlite3.c
	$(CC) $(CFLAGS) -o $(TARGET) ai_block.c sqlite3.c $(LIBS)

clean:
	rm -f $(TARGET)
